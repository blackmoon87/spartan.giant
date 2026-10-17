# Database — models, query builder, relations, migrations

Source: `framework/src/Model.php`, `QueryBuilder.php`, `RelationQuery.php`,
`Database/Migrator.php`, `ConnectionManager.php`.

## Connection

Set in `.env`; read by `config/config.php`:

```ini
DB_CONNECTION=mysql        # or sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp          # sqlite: a file path relative to the project root
DB_USERNAME=root
DB_PASSWORD=
```

The connection opens lazily on first use. The PDO object is `Application::$app->db`
(`null` when no database is configured). Errors throw `PDOException`.

## Model template

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Post extends Model
{
    protected string $table = 'posts';

    // Optional: fill created_at / updated_at (UTC) in create() and save().
    protected bool $timestamps = true;

    /** Domain query: keep SQL here, not in controllers. */
    public function published(int $limit = 10): array
    {
        return $this->table()
            ->where('status', 'published')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function author(): RelationQuery
    {
        return $this->belongsTo(User::class, foreignKey: 'user_id');
    }
}
```

Use a model with `new`: `$posts = (new Post())->published();`

## Model methods

| Method | Returns | Notes |
|---|---|---|
| `table(string $table = '')` | `QueryBuilder` | the model's table, or another one |
| `find(int\|string $id)` | `?array` | row by `id` |
| `findInstance(int\|string $id)` | `?static` | hydrated model; columns as properties (`$user->email`) |
| `findInstanceBy(string $col, mixed $value)` | `?static` | first match, hydrated |
| `all()` | `array` | every row |
| `create(array $data)` | `string\|false` | inserts, returns new id |
| `save(int\|string $id, array $data)` | `int` | updates row `id`, returns affected rows |
| `toArray()` | `array` | a hydrated model's columns |
| `fresh()` | `?static` | re-read a hydrated model from the database |
| `transaction(callable $fn)` | `mixed` | see Transactions |
| `query(string $sql, array $params = [])` | `PDOStatement` | protected: raw SQL inside the model, `?` placeholders |

`find`/`all`/query-builder reads return **arrays** (`$row['email']`); only
`findInstance`/`findInstanceBy` return model objects.

## Query builder — `$this->table()`

Reads:

```php
$q = $this->table();                       // or $this->table('other_table')
$q->select('id', 'name', 'email');         // or select('id, name')
$q->where('active', 1);                    // column, value, operator (default '=')
$q->where('price', 100, '>=');             // value BEFORE operator
$q->where('role', ['admin', 'editor']);    // array value → IN (empty array → no rows)
$q->whereNotIn('id', [1, 2]);
$q->whereNull('deleted_at');  $q->whereNotNull('email');
$q->whereBetween('price', [10, 50]);
$q->orWhere('featured', 1);                // or… variants exist for every where*
$q->where(function (\Spartan\QueryBuilder $w) {   // grouped: ( … OR … )
    $w->where('status', 'draft')->orWhere('status', 'review');
});
$q->join('users', 'users.id', '=', 'posts.user_id');   // also leftJoin, rightJoin
$q->groupBy('category_id')->having('total', 5, '>=');
$q->orderBy('created_at', 'DESC')->limit(20)->offset(40);
```

Terminators:

| Call | Returns |
|---|---|
| `get()` | `array` of rows |
| `first()` | `?array` |
| `find($id)` | `?array` |
| `count()` | `int` |
| `exists()` | `bool` |
| `pluck('email')` / `pluck('name', 'id')` | `array` (list, or keyed by the 2nd column) |
| `paginate(15, $page)` | `['data' => rows, 'total', 'per_page', 'current_page', 'last_page']` |
| `chunk(500, fn(array $rows, int $page) => …)` | `bool`; return `false` from the callback to stop |
| `cursor()` | `Generator` of rows (one at a time, constant memory) |

Writes:

| Call | Returns | Notes |
|---|---|---|
| `insert(array $data)` | `string\|false` | new id |
| `where(…)->update(array $data)` | `int` | **requires a where()**, else `LogicException` |
| `where(…)->delete()` | `int` | **requires a where()** |
| `where(…)->increment('views', 1, ['updated_at' => $now])` | `int` | atomic; `decrement` too |
| `truncate()` | `int` | deletes all rows on purpose |

Operators accepted: `= != <> < > <= >= <=> LIKE, NOT LIKE, ILIKE, IN, NOT IN, IS, IS NOT`.

Column names may be `col`, `table.col`, `col as alias`, or a function from this
list: `COUNT SUM AVG MIN MAX LOWER UPPER LENGTH CHAR_LENGTH ABS ROUND COALESCE
IFNULL DATE YEAR MONTH DAY TRIM` — e.g. `select('COUNT(*) as total')`,
`select("COALESCE(role, 'none') as r")`. Anything else throws `RuntimeException`.

**User-controlled sorting:** map the request value through your own allowlist —
`$col = ['newest' => 'created_at', 'title' => 'title'][$sort] ?? 'created_at';`
— then `orderBy($col)`.

`join('t', 'a.x = b.y')` with only two arguments is a **raw** ON clause: build it
from constants only.

## Relations

Define in the model (each returns a `RelationQuery`):

```php
public function posts(): RelationQuery   { return $this->hasMany(Post::class, foreignKey: 'user_id'); }
public function profile(): RelationQuery { return $this->hasOne(Profile::class, foreignKey: 'user_id'); }
public function author(): RelationQuery  { return $this->belongsTo(User::class, foreignKey: 'user_id'); }
```

Signatures: `hasMany(class, foreignKey, localKey = 'id')`, `hasOne(…same…)`,
`belongsTo(class, foreignKey, ownerKey = 'id')`.

Use:

```php
$user  = (new User())->find(1);
$posts = (new User())->posts()->for($user);            // hasMany  → array of rows
$owner = (new Post())->author()->for($post);           // belongsTo/hasOne → ?array

// Eager loading, one extra query for the whole list (no N+1):
$users = (new User())->all();
$users = (new User())->posts()->loadFor($users, 'posts');   // each row gains ['posts' => [...]]
```

## Transactions

```php
$orderId = (new Order())->transaction(function (Order $orders) use ($data) {
    $id = $orders->create(['user_id' => $data['user_id'], 'total' => $data['total']]);
    (new Stock())->table()->where('sku', $data['sku'])->decrement('qty', 1);
    return $id;                       // commit; any exception → rollback + rethrow
});

// Or without a model:  \Spartan\Database::transaction(function (\PDO $db) { … });
```

Nested calls join the outer transaction.

## Migrations

Files in `database/migrations/`, run in filename order:

```
0003_create_posts_table.sql
0003_create_posts_table_down.sql      ← rollback (optional)
```

Write MySQL syntax; on SQLite it is translated automatically (`AUTO_INCREMENT`,
`ENUM` → `VARCHAR(50)`, `JSON` → `TEXT`, `UNSIGNED`/`ENGINE`/`CHARSET` removed):

```sql
CREATE TABLE IF NOT EXISTS posts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(255) NOT NULL,
    status     ENUM('draft','published') NOT NULL DEFAULT 'draft',
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);
CREATE INDEX idx_posts_user ON posts (user_id);
```

| Command | Effect |
|---|---|
| `php spartan migrate` | run pending migrations (one batch) |
| `php spartan migrate:status` | list ran / pending |
| `php spartan migrate:rollback [N]` | undo the last N batches (runs `_down.sql`) |
| `php spartan migrate:reset` | undo all batches |
| `php spartan migrate:fresh` | **drop every table** in the database, then migrate |
| `php spartan db:seed` | execute `database/seed.sql` |

On SQLite each file runs in a transaction (all or nothing). On MySQL, DDL
commits per statement; a failure reports which statement failed.

## More than one database

```php
Application::$app->connections->addConnection('analytics', [
    'connection' => 'mysql', 'host' => '…', 'database' => 'analytics',
    'username' => '…', 'password' => '…',
]);
$rows = (new \Spartan\QueryBuilder(Application::$app->connections->connection('analytics'), 'events'))
    ->where('type', 'signup')->get();
```

Read replicas: set `DB_READ_WRITE_SPLIT=true` and `DB_READ_HOST_1` (`DB_READ_PORT_1`)
in `.env`. `Model::table()` then sends reads to a replica, writes to the primary,
and keeps reads on the primary inside a transaction and for the rest of a request
that has written.
