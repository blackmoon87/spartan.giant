<?php

declare(strict_types=1);

namespace Spartan;

use PDO;
use RuntimeException;

/**
 * @phpstan-consistent-constructor
 */
abstract class Model
{
    protected ?PDO  $db        = null;
    protected string $table    = '';
    protected array $attributes = [];

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Set to true to automatically manage created_at and updated_at timestamps.
     * When enabled:
     *   - create() appends both created_at and updated_at (current UTC datetime)
     *   - save()   appends updated_at (current UTC datetime)
     * The table must have these columns defined as DATETIME or TIMESTAMP.
     * Off by default — no breaking change to existing models.
     */
    protected bool $timestamps = false;

    /**
     * UTC datetime format used for timestamp columns.
     */
    private const TS_FORMAT = 'Y-m-d H:i:s';

    public function __construct()
    {
        $this->db = Application::$app->db;
    }

    /**
     * Get the database table name associated with the model.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Helper to prepare and execute SQL statements safely.
     * Declared protected to enforce that SQL logic remains strictly inside Models,
     * protecting it from leaking into Controllers or Views.
     */
    protected function query(string $sql, array $params = []): \PDOStatement
    {
        if ($this->db === null) {
            throw new RuntimeException("Database connection is not configured or failed to initialize.");
        }
        
        $stmt = $this->db->prepare($sql);
        QueryBuilder::bindTyped($stmt, $params);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Open a fluent QueryBuilder for any table.
     * If no table is provided, uses the model's own $table property.
     *
     * Usage:
     *   $this->table()->where('active', 1)->orderBy('name')->get();
     *   $this->table('orders')->where('user_id', $id)->count();
     */
    public function table(string $tableName = ''): QueryBuilder
    {
        $target = $tableName ?: $this->table;

        if (empty($target)) {
            throw new RuntimeException(
                'No table specified. Either set $table on the model or pass a table name to table().' 
            );
        }

        if ($this->db === null) {
            throw new RuntimeException('Database connection is not configured or failed to initialize.');
        }

        return new QueryBuilder($this->db, $target);
    }

    /**
     * Retrieve a record by its primary key ID.
     */
    public function find(int|string $id): ?array
    {
        return $this->table()->find($id);
    }

    /**
     * Find a record by ID and return a hydrated Model instance.
     */
    public function findInstance(int|string $id): ?static
    {
        $row = $this->find($id);
        if (!$row) {
            return null;
        }

        $instance = new static();
        foreach ($row as $key => $value) {
            $instance->$key = $value;
        }
        return $instance;
    }

    /**
     * Find a record by a custom column and return a hydrated Model instance.
     */
    public function findInstanceBy(string $column, mixed $value): ?static
    {
        $row = $this->table()->where($column, $value)->first();
        if (!$row) {
            return null;
        }

        $instance = new static();
        foreach ($row as $key => $val) {
            $instance->$key = $val;
        }
        return $instance;
    }

    /**
     * Retrieve all records belonging to the model's table.
     */
    public function all(): array
    {
        return $this->table()->get();
    }

    /**
     * Per-request in-memory memoization store.
     * Maps query keys -> cached result arrays/objects for 0.00ms intra-request access.
     */
    protected static array $memoizedStore = [];

    /**
     * Memoize a calculation or query result for the duration of the current HTTP request.
     * Re-uses the cached value on subsequent calls within the same request lifecycle (0.00ms).
     */
    public static function memoize(string $key, callable $callback): mixed
    {
        if (array_key_exists($key, self::$memoizedStore)) {
            return self::$memoizedStore[$key];
        }
        return self::$memoizedStore[$key] = $callback();
    }

    /**
     * Invalidate per-request memoized entries for a specific table, or purge the entire store.
     */
    public static function forgetMemoize(?string $table = null): void
    {
        if ($table === null) {
            self::$memoizedStore = [];
        } else {
            foreach (array_keys(self::$memoizedStore) as $key) {
                if (str_starts_with($key, $table . ':') || str_starts_with($key, $table . '.')) {
                    unset(self::$memoizedStore[$key]);
                }
            }
        }
    }

    /**
     * Re-fetch a fresh copy of the model instance directly from the database,
     * bypassing any in-memory memoized state.
     */
    public function fresh(): ?static
    {
        $id = $this->attributes['id'] ?? null;
        if ($id === null) {
            return null;
        }
        self::forgetMemoize($this->getTable());
        return $this->findInstance($id);
    }

    /**
     * Insert a new row, optionally auto-stamping created_at and updated_at.
     * Returns the last inserted ID.
     *
     * Use this instead of calling $this->table()->insert() directly when
     * you have $timestamps = true, so timestamps are applied automatically.
     */
    public function create(array $data): string|false
    {
        self::forgetMemoize($this->getTable());
        if (isset(Application::$app) && isset(Application::$app->auth)) {
            Application::$app->auth->forgetUser();
        }
        if ($this->timestamps) {
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(self::TS_FORMAT);
            $data['created_at'] ??= $now;
            $data['updated_at'] ??= $now;
        }
        return $this->table()->insert($data);
    }

    /**
     * Update a row by primary key, optionally auto-stamping updated_at.
     * Returns the number of affected rows.
     *
     * Use this instead of calling $this->table()->where('id',$id)->update()
     * directly when you have $timestamps = true.
     */
    public function save(int|string $id, array $data): int
    {
        self::forgetMemoize($this->getTable());
        if (isset(Application::$app) && isset(Application::$app->auth)) {
            $currentUserId = Application::$app->auth->id();
            if ((string)$currentUserId === (string)$id) {
                Application::$app->auth->forgetUser();
            }
        }
        if ($this->timestamps) {
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(self::TS_FORMAT);
            // Consistent with create(): an explicitly supplied timestamp wins.
            $data['updated_at'] ??= $now;
        }
        return $this->table()->where('id', $id)->update($data);
    }

    /**
     * Return all model attributes as a plain associative array.
     * Useful for JSON serialization and passing data to views.
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->db ? $this->db->beginTransaction() : false;
    }

    /**
     * Commit the current database transaction.
     */
    public function commit(): bool
    {
        return $this->db ? $this->db->commit() : false;
    }

    /**
     * Roll back the current database transaction.
     */
    public function rollBack(): bool
    {
        return $this->db ? $this->db->rollBack() : false;
    }

    /**
     * Run a callback within a database transaction.
     * Automatically commits on success and rolls back on exception.
     *
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->db === null) {
            throw new RuntimeException('Database connection is not configured or failed to initialize.');
        }

        // Nested calls join the outer transaction instead of opening a second
        // one (PDO would throw) — and must not commit or roll it back.
        $isOuter = !$this->db->inTransaction();
        if ($isOuter) {
            $this->beginTransaction();
        }

        try {
            $result = $callback($this);
            if ($isOuter) {
                $this->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($isOuter && $this->db->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Define a one-to-many relationship.
     * The foreign key lives on the RELATED table.
     *
     * Usage in model:
     *   public function orders(): RelationQuery
     *   {
     *       return $this->hasMany(Order::class, foreignKey: 'user_id');
     *   }
     *
     * Usage in controller:
     *   $user   = (new User)->find(1);
     *   $orders = (new User)->orders()->for($user);            // single user
     *   $users  = (new User)->orders()->loadFor($users, 'orders'); // collection
     *
     * @param string $relatedClass  Fully-qualified class name of the related Model
     * @param string $foreignKey    Column on the related table pointing back to this table
     * @param string $localKey      Primary key on this table (default: 'id')
     */
    protected function hasMany(
        string $relatedClass,
        string $foreignKey,
        string $localKey = 'id'
    ): RelationQuery {
        return $this->buildRelation('hasMany', $relatedClass, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-one relationship.
     * The foreign key lives on the RELATED table.
     *
     * Usage in model:
     *   public function profile(): RelationQuery
     *   {
     *       return $this->hasOne(Profile::class, foreignKey: 'user_id');
     *   }
     *
     * @param string $relatedClass  Fully-qualified class name of the related Model
     * @param string $foreignKey    Column on the related table pointing back to this table
     * @param string $localKey      Primary key on this table (default: 'id')
     */
    protected function hasOne(
        string $relatedClass,
        string $foreignKey,
        string $localKey = 'id'
    ): RelationQuery {
        return $this->buildRelation('hasOne', $relatedClass, $foreignKey, $localKey);
    }

    /**
     * Define an inverse relationship (many-to-one).
     * The foreign key lives on THIS model's table.
     *
     * Usage in model:
     *   public function user(): RelationQuery
     *   {
     *       return $this->belongsTo(User::class, foreignKey: 'user_id');
     *   }
     *
     * Usage in controller:
     *   $order = (new Order)->find(5);
     *   $user  = (new Order)->user()->for($order);             // single order
     *   $orders = (new Order)->user()->loadFor($orders, 'user'); // collection
     *
     * @param string $relatedClass  Fully-qualified class name of the owner Model
     * @param string $foreignKey    Column on THIS table holding the owner's ID
     * @param string $ownerKey      Primary key on the owner table (default: 'id')
     */
    protected function belongsTo(
        string $relatedClass,
        string $foreignKey,
        string $ownerKey = 'id'
    ): RelationQuery {
        return $this->buildRelation('belongsTo', $relatedClass, $foreignKey, $ownerKey);
    }

    /**
     * Resolve the related model's table name and instantiate a RelationQuery.
     */
    private function buildRelation(
        string $type,
        string $relatedClass,
        string $foreignKey,
        string $localKey
    ): RelationQuery {
        if ($this->db === null) {
            throw new RuntimeException('Database connection is not configured or failed to initialize.');
        }

        if (!class_exists($relatedClass)) {
            throw new \InvalidArgumentException(
                "Relation target [{$relatedClass}] does not exist."
            );
        }

        // Resolve related table without Reflection
        $relatedTable = (new $relatedClass())->getTable();

        if (empty($relatedTable)) {
            throw new RuntimeException(
                "Related model [{$relatedClass}] must define a \$table property."
            );
        }

        return new RelationQuery(
            type:         $type,
            relatedClass: $relatedClass,
            relatedTable: $relatedTable,
            foreignKey:   $foreignKey,
            localKey:     $localKey,
            db:           $this->db
        );
    }
}

