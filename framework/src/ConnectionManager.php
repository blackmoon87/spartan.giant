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
        unset($this->connections[$name]);
    }

    /**
     * Close all active connections.
     * Call this in worker mode between requests to prevent connection leaks.
     */
    public function disconnectAll(): void
    {
        $this->connections = [];
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
     * Falls back to 'default' if no replicas are configured.
     */
    private function getReadReplica(): PDO
    {
        if (empty($this->readReplicas)) {
            return $this->connection('default');
        }

        // Pick a random replica for basic load distribution
        $index = array_rand($this->readReplicas);
        $replicaName = 'read_' . $index;

        if (isset($this->connections[$replicaName])) {
            return $this->connections[$replicaName];
        }

        // Merge replica-specific config over the default config
        $baseConfig = $this->configs['default'] ?? [];
        $replicaConfig = array_merge($baseConfig, $this->readReplicas[$index]);

        $this->connections[$replicaName] = $this->createPdo($replicaConfig);
        return $this->connections[$replicaName];
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
