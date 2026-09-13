<?php

declare(strict_types=1);

namespace Spartan;

use PDO;
use PDOException;

/**
 * Multi-connection Database Manager.
 *
 * Manages named PDO connections with lazy instantiation and optional
 * read/write splitting. When splitting is enabled, SELECT queries
 * route to a randomly chosen read replica while writes always go
 * to the primary.
 *
 * Usage:
 *   // Default single-connection mode (backward-compatible):
 *   $pdo = $manager->connection();          // same as connection('default')
 *
 *   // Explicit read/write routing:
 *   $pdo = $manager->connection('write');   // always the primary
 *   $pdo = $manager->connection('read');    // random replica, or primary fallback
 *
 *   // Named connections for multi-database setups:
 *   $manager->addConnection('analytics', $analyticsConfig);
 *   $pdo = $manager->connection('analytics');
 *
 * Configuration:
 *   Set DB_READ_WRITE_SPLIT=true in .env to enable splitting.
 *   Define read replicas via DB_READ_HOST_1, DB_READ_HOST_2, etc.
 *   When splitting is disabled, 'read' and 'write' both resolve to 'default'.
 */
class ConnectionManager
{
    /** @var array<string, PDO> Active PDO instances, keyed by connection name */
    private array $connections = [];

    /** @var array<string, array> Connection configs, keyed by connection name */
    private array $configs = [];

    /** @var list<array> Read replica configurations */
    private array $readReplicas = [];

    /** @var bool Whether read/write splitting is enabled */
    private bool $splitEnabled = false;

    /** @var array<string, int> Unix timestamp each open connection was created, keyed by name */
    private array $connectedAt = [];

    /**
     * Maximum age (seconds) a connection may reach before recycleStale()
     * forces a reconnect on its next use. 0 disables the age check.
     */
    private int $maxLifetime = 3600;

    /**
     * @var array<int, int> Replica index → unix timestamp until which it is
     * skipped after a failed connection attempt (failover cooldown).
     */
    private array $replicaDeadUntil = [];

    /** Seconds a failed replica is skipped before being retried. */
    private const REPLICA_COOLDOWN = 30;

    /**
     * Register a named connection configuration.
     * The connection is NOT opened until connection() is called.
     *
     * @param string $name   Connection name ('default', 'analytics', etc.)
     * @param array  $config PDO configuration array matching config/config.php['db']
     */
    public function addConnection(string $name, array $config): void
    {
        $this->configs[$name] = $config;
        // Invalidate any existing connection so the next call re-connects.
        unset($this->connections[$name]);
    }

    /**
     * Register a read replica configuration.
     * Replicas share the database name, username, and password of the
     * 'default' connection — only host and port may differ.
     *
     * @param array $config Partial config: at minimum ['host' => '...']
     */
    public function addReadReplica(array $config): void
    {
        if (!empty($config['host'])) {
            $this->readReplicas[] = $config;
        }
    }

    /**
     * Enable or disable read/write splitting.
     * When disabled, connection('read') falls back to connection('default').
     */
    public function enableSplit(bool $enabled = true): void
    {
        $this->splitEnabled = $enabled;
    }

    /**
     * Set the maximum age (seconds) a connection may reach before
     * recycleStale() forces a reconnect. 0 disables the age check.
     */
    public function setMaxLifetime(int $seconds): void
    {
        $this->maxLifetime = max(0, $seconds);
    }

    /**
     * Whether read/write splitting is currently active.
     */
    public function isSplitEnabled(): bool
    {
        return $this->splitEnabled && !empty($this->readReplicas);
    }

    /**
     * Get a PDO connection by name.
     *
     * Special names:
     *   'default' — the primary connection
     *   'write'   — alias for 'default' (always the primary)
     *   'read'    — a randomly chosen read replica (falls back to 'default')
     *
     * @throws PDOException if the connection cannot be established
     */
    public function connection(string $name = 'default'): PDO
    {
        // 'write' always resolves to the primary
        if ($name === 'write') {
            $name = 'default';
        }

        // 'read' routes to a replica when splitting is active
        if ($name === 'read') {
            if ($this->isSplitEnabled()) {
                return $this->getReadReplica();
            }
            $name = 'default';
        }

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        if (!isset($this->configs[$name])) {
            throw new PDOException(
                "ConnectionManager: No configuration registered for connection [{$name}]."
            );
        }

        $this->connections[$name] = $this->createPdo($this->configs[$name]);
        $this->connectedAt[$name] = time();
        return $this->connections[$name];
    }

    /**
     * Check if a connection configuration is registered.
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->configs[$name]);
    }

    /**
     * Check if a connection is currently open (active PDO instance).
     */
    public function isConnected(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Close a specific connection.
     */
    public function disconnect(string $name = 'default'): void
    {
        unset($this->connections[$name], $this->connectedAt[$name]);
    }

    /**
     * Close all active connections.
     * Call this in worker mode between requests to prevent connection leaks.
     */
    public function disconnectAll(): void
    {
        $this->connections = [];
        $this->connectedAt = [];
    }

    /**
     * Ping a single open connection with a trivial query.
     * Returns false (without throwing) if the connection is closed, dropped,
     * or not currently open.
     */
    public function ping(string $name = 'default'): bool
    {
        if (!isset($this->connections[$name])) {
            return false;
        }
        try {
            $this->connections[$name]->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Health-check every open connection and recycle the ones that failed a
     * ping or exceeded the configured max lifetime — a dropped/aged
     * connection is closed so the next connection() call transparently
     * reconnects. Healthy connections are left completely untouched, which
     * is what keeps a worker-mode connection warm across requests instead of
     * tearing every connection down after each one.
     */
    public function recycleStale(): void
    {
        $now = time();
        foreach (array_keys($this->connections) as $name) {
            $age = $now - ($this->connectedAt[$name] ?? $now);
            $tooOld = $this->maxLifetime > 0 && $age >= $this->maxLifetime;

            if ($tooOld || !$this->ping($name)) {
                $this->disconnect($name);
            }
        }
    }

    /**
     * Return the names of all currently open connections.
     *
     * @return list<string>
     */
    public function getActiveConnectionNames(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Return the names of all registered connection configurations.
     *
     * @return list<string>
     */
    public function getRegisteredConnectionNames(): array
    {
        return array_keys($this->configs);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get or create a connection to a random read replica.
     * Falls back to 'default' if no replicas are configured, if every
     * replica is currently in its failure cooldown, or if connecting to the
     * chosen replica fails outright — a down replica must never fail the
     * request, only degrade it to reading from the primary.
     */
    private function getReadReplica(): PDO
    {
        if (empty($this->readReplicas)) {
            return $this->connection('default');
        }

        $now = time();
        $healthy = array_filter(
            array_keys($this->readReplicas),
            fn($i) => ($this->replicaDeadUntil[$i] ?? 0) <= $now
        );

        if (empty($healthy)) {
            return $this->connection('default');
        }

        // Pick a random healthy replica for basic load distribution
        $index = $healthy[array_rand($healthy)];
        $replicaName = 'read_' . $index;

        if (isset($this->connections[$replicaName])) {
            return $this->connections[$replicaName];
        }

        // Merge replica-specific config over the default config
        $baseConfig = $this->configs['default'] ?? [];
        $replicaConfig = array_merge($baseConfig, $this->readReplicas[$index]);

        try {
            $this->connections[$replicaName] = $this->createPdo($replicaConfig);
            $this->connectedAt[$replicaName] = time();
            unset($this->replicaDeadUntil[$index]);
            return $this->connections[$replicaName];
        } catch (PDOException) {
            $this->replicaDeadUntil[$index] = $now + self::REPLICA_COOLDOWN;
            return $this->connection('default');
        }
    }

    /**
     * Create a PDO instance from a configuration array.
     *
     * @throws PDOException
     */
    private function createPdo(array $config): PDO
    {
        $connection = $config['connection'] ?? 'mysql';

        if ($connection === 'sqlite') {
            $dbPath = $config['database'] ?? ':memory:';
            if ($dbPath !== ':memory:' && !str_starts_with($dbPath, '/') && !preg_match('#^[a-zA-Z]:\\\\#', $dbPath)) {
                $dbPath = Paths::base($dbPath);
            }
            $dsn = 'sqlite:' . $dbPath;
        } else {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $connection,
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['database'] ?? ''
            );
        }

        try {
            return new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new PDOException(
                "ConnectionManager: Connection failed: " . $e->getMessage(),
                (int) $e->getCode()
            );
        }
    }
}
