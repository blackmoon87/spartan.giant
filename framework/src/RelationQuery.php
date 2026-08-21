<?php

declare(strict_types=1);

namespace Spartan;

use PDO;
use RuntimeException;

/**
 * RelationQuery — Lightweight Model Relationship Executor.
 *
 * Returned by Model::hasMany(), hasOne(), and belongsTo().
 * Executes the relationship query lazily — only when for() or loadFor() is called.
 *
 * ── Single record ────────────────────────────────────────────────────────────
 *
 *   $user   = (new User)->find(1);
 *   $orders = (new User)->orders()->for($user);     // array of rows
 *   $profile = (new User)->profile()->for($user);   // single row or null
 *
 *   $order = (new Order)->find(5);
 *   $user  = (new Order)->user()->for($order);      // single row or null
 *
 * ── Collection — Eager Loading (no N+1) ──────────────────────────────────────
 *
 *   $users = (new User)->all();                                    // query 1
 *   $users = (new User)->orders()->loadFor($users, as: 'orders'); // query 2
 *   // Result: each user array gains an 'orders' key
 *   // $users[0]['orders'] → [row, row, ...]
 *   // $users[1]['orders'] → [row, ...]
 */
class RelationQuery
{
    /**
     * @param string $type          'hasMany' | 'hasOne' | 'belongsTo'
     * @param string $relatedClass  Fully-qualified related model class (e.g. App\Models\Order)
     * @param string $relatedTable  DB table name of the related model
     * @param string $foreignKey    The foreign key column name
     * @param string $localKey      The primary key on the local/related side (almost always 'id')
     * @param PDO    $db
     */
    public function __construct(
        private readonly string $type,
        private readonly string $relatedClass,
        private readonly string $relatedTable,
        private readonly string $foreignKey,
        private readonly string $localKey,
        private readonly PDO    $db
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Single Record
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Execute the relationship for a single parent record.
     *
     * hasMany   → returns array  (all matching related rows)
     * hasOne    → returns ?array (first matching related row or null)
     * belongsTo → returns ?array (the single parent/owner record or null)
     *
     * @param  array $parent The parent row array (e.g. $user, $order)
     * @return array|null
     */
    public function for(array|Model $parent): array|null
    {
        $parentData = $parent instanceof Model ? $parent->toArray() : $parent;
        return match ($this->type) {
            'hasMany'    => $this->resolveForeignQuery($parentData)->get(),
            'hasOne'     => $this->resolveForeignQuery($parentData)->first(),
            'belongsTo'  => $this->resolveOwnerQuery($parentData)->first(),
            default      => throw new RuntimeException("Unknown relation type [{$this->type}]."),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Collection — Eager Loading
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Eager-load the relationship for an entire collection.
     * Executes exactly ONE extra query regardless of collection size.
     *
     * @param  array  $records Collection of parent row arrays
     * @param  string $as      The key to attach related data under (e.g. 'orders', 'user')
     * @return array  The same $records with $as key added to each row
     */
    public function loadFor(array $records, string $as = ''): array
    {
        if (empty($records)) {
            return $records;
        }

        $key = $as ?: $this->defaultKey();

        if ($this->type === 'belongsTo') {
            return $this->eagerBelongsTo($records, $key);
        }

        return $this->eagerHasMany($records, $key);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal — Single Query Builders
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a QueryBuilder for hasMany / hasOne:
     *   WHERE foreignKey = $parent[localKey]
     */
    private function resolveForeignQuery(array $parent): QueryBuilder
    {
        $parentKeyValue = $parent[$this->localKey] ?? null;

        if ($parentKeyValue === null) {
            throw new RuntimeException(
                "RelationQuery ({$this->type}): parent record is missing the key [{$this->localKey}]."
            );
        }

        return (new QueryBuilder($this->db, $this->relatedTable))
            ->where($this->foreignKey, $parentKeyValue);
    }

    /**
     * Build a QueryBuilder for belongsTo:
     *   WHERE localKey = $parent[foreignKey]
     */
    private function resolveOwnerQuery(array $parent): QueryBuilder
    {
        $fkValue = $parent[$this->foreignKey] ?? null;

        if ($fkValue === null) {
            throw new RuntimeException(
                "RelationQuery (belongsTo): parent record is missing the foreign key column [{$this->foreignKey}]."
            );
        }

        return (new QueryBuilder($this->db, $this->relatedTable))
            ->where($this->localKey, $fkValue);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal — Eager Loading
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Eager load for hasMany / hasOne.
     *
     * Extracts all parent IDs → one IN query → groups results by foreignKey.
     *
     *   Parent: users [id=1, id=2, id=3]
     *   Query:  SELECT * FROM orders WHERE user_id IN (1, 2, 3)
     *   Groups: { 1 => [orders], 2 => [orders], 3 => [] }
     */
    private function eagerHasMany(array $records, string $key): array
    {
        // Extract parent key values (e.g. all user IDs)
        $ids = array_values(array_unique(array_column($records, $this->localKey)));
        $ids = array_filter($ids, fn($v) => $v !== null);

        if (empty($ids)) {
            // Attach empty results
            return array_map(fn($r) => array_merge($r, [$key => []]), $records);
        }

        // One query: SELECT * FROM related WHERE foreignKey IN (ids)
        $related = (new QueryBuilder($this->db, $this->relatedTable))
            ->where($this->foreignKey, $ids, 'IN')
            ->get();

        // Group related records by their foreignKey value
        $grouped = [];
        foreach ($related as $row) {
            $grouped[$row[$this->foreignKey]][] = $row;
        }

        // Attach to parent records
        return array_map(function (array $record) use ($grouped, $key) {
            $parentId = $record[$this->localKey] ?? null;
            $related  = $grouped[$parentId] ?? [];

            // hasOne: attach only the first match
            if ($this->type === 'hasOne') {
                $record[$key] = $related[0] ?? null;
            } else {
                $record[$key] = $related;
            }

            return $record;
        }, $records);
    }

    /**
     * Eager load for belongsTo.
     *
     * Extracts all foreign key values → one IN query on related PK → maps by ID.
     *
     *   Parent: orders [user_id=1, user_id=2, user_id=1]
     *   Query:  SELECT * FROM users WHERE id IN (1, 2)
     *   Maps:   { 1 => user_row, 2 => user_row }
     */
    private function eagerBelongsTo(array $records, string $key): array
    {
        // Extract FK values from parent records (e.g. all user_id values from orders)
        $fkValues = array_values(array_unique(array_column($records, $this->foreignKey)));
        $fkValues = array_filter($fkValues, fn($v) => $v !== null);

        if (empty($fkValues)) {
            return array_map(fn($r) => array_merge($r, [$key => null]), $records);
        }

        // One query: SELECT * FROM related WHERE localKey IN (fkValues)
        $related = (new QueryBuilder($this->db, $this->relatedTable))
            ->where($this->localKey, $fkValues, 'IN')
            ->get();

        // Index related records by their PK for O(1) lookup
        $indexed = [];
        foreach ($related as $row) {
            $indexed[$row[$this->localKey]] = $row;
        }

        // Attach to parent records
        return array_map(function (array $record) use ($indexed, $key) {
            $fkValue     = $record[$this->foreignKey] ?? null;
            $record[$key] = $fkValue !== null ? ($indexed[$fkValue] ?? null) : null;
            return $record;
        }, $records);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Derive a default key name from the related class for loadFor().
     * App\Models\Order → 'order'    (hasOne/belongsTo)
     * App\Models\Order → 'orders'   (hasMany)
     */
    private function defaultKey(): string
    {
        $parts = explode('\\', $this->relatedClass);
        $base  = strtolower(end($parts));

        if ($this->type !== 'hasMany') {
            return $base;
        }

        // Basic English pluralization rules
        if (str_ends_with($base, 'y') && !in_array(substr($base, -2, 1), ['a','e','i','o','u'], true)) {
            return substr($base, 0, -1) . 'ies'; // category → categories
        }
        if (str_ends_with($base, 's') || str_ends_with($base, 'x') ||
            str_ends_with($base, 'z') || str_ends_with($base, 'ch') || str_ends_with($base, 'sh')) {
            return $base . 'es'; // box → boxes, match → matches
        }
        return $base . 's'; // order → orders
    }
}
