<?php

declare(strict_types=1);

namespace Spartan\Tests\Fixtures;

use Spartan\Attributes\RequirePermission;
use Spartan\Attributes\RequireRole;
use Spartan\Controller;
use Spartan\FormRequest;
use Spartan\Logger;
use Spartan\Middleware;
use Spartan\Model;
use Spartan\RelationQuery;
use Spartan\Request;
use Spartan\Response;

/** Records ordered side effects so tests can assert on execution order. */
final class Trace
{
    public static array $log = [];

    public static function reset(): void
    {
        self::$log = [];
    }
}

final class Stringy
{
    public function __toString(): string
    {
        return 'stringable-message';
    }
}

// ─── Container fixtures ─────────────────────────────────────────────────────
final class NeedsLogger
{
    public function __construct(public Logger $logger) {}
}

final class HasDefault
{
    public function __construct(public int $depth = 7) {}
}

final class Unresolvable
{
    public function __construct(public string $required) {}
}

// ─── Gate fixtures ──────────────────────────────────────────────────────────
final class Article
{
    public function __construct(public int $id) {}
}

final class ArticlePolicy
{
    public function update(?object $user, Article $article): bool
    {
        return $article->id === 5;
    }
}

final class RoleUser
{
    public function __construct(private string $role, private array $permissions) {}

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}

// ─── Models ─────────────────────────────────────────────────────────────────
class UserModel extends Model
{
    protected string $table = 't_users';
    protected bool $timestamps = true;

    public function posts(): RelationQuery
    {
        return $this->hasMany(PostModel::class, 'user_id');
    }

    public function latestPost(): RelationQuery
    {
        return $this->hasOne(PostModel::class, 'user_id');
    }

    public function brokenRelation(): RelationQuery
    {
        return $this->hasMany('No\\Such\\Model', 'user_id');
    }
}

class PostModel extends Model
{
    protected string $table = 't_posts';

    public function author(): RelationQuery
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

class TablelessModel extends Model {}

// ─── Controllers ────────────────────────────────────────────────────────────
class PlainController extends Controller
{
    public function plain(): string
    {
        return 'plain-ok';
    }

    public function withRequest(Request $request): string
    {
        return 'path=' . $request->getPath();
    }

    public function withFormRequest(EmailRequest $request): string
    {
        return 'valid:' . $request->post('email');
    }

    public function mixed(Request $request, $id): string
    {
        return 'req+' . $id;
    }

    public function needsParam(int $id): string
    {
        return 'never';
    }

    public function withDefault(int $page = 1): string
    {
        return 'page=' . $page;
    }

    public function hasServices(): bool
    {
        return $this->request instanceof Request && $this->response instanceof Response;
    }
}

#[RequireRole('admin')]
#[RequirePermission('manage_users')]
class AdminController extends Controller
{
    public function index(): string
    {
        return 'admin-index';
    }

    #[RequirePermission('delete_everything')]
    public function danger(): string
    {
        return 'danger';
    }
}

// ─── Form requests ──────────────────────────────────────────────────────────
class EmailRequest extends FormRequest
{
    public function rules(): array
    {
        return ['email' => 'required|email'];
    }
}

class DeniedRequest extends FormRequest
{
    public function rules(): array
    {
        return ['email' => 'required'];
    }

    public function authorize(): bool
    {
        return false;
    }
}

// ─── Middleware ─────────────────────────────────────────────────────────────
class PassMiddleware extends Middleware
{
    public function execute(Request $request, Response $response): void
    {
        Trace::$log[] = 'pass';
    }
}

class SecondMiddleware extends Middleware
{
    public function execute(Request $request, Response $response): void
    {
        Trace::$log[] = 'second';
    }
}

class BlockMiddleware extends Middleware
{
    public function execute(Request $request, Response $response): void
    {
        $response->setStatusCode(403);
    }
}

class ArgsMiddleware extends Middleware
{
    public function __construct(private int $limit = 0, private int $window = 0) {}

    public function execute(Request $request, Response $response): void
    {
        Trace::$log[] = sprintf('args:%d:%d', $this->limit, $this->window);
    }
}

// ─── Listeners ──────────────────────────────────────────────────────────────
class SyncListener
{
    public function handle(mixed $payload): void
    {
        Trace::$log[] = 'sync:' . ($payload['v'] ?? '?');
    }
}

class AsyncListener
{
    public function handle(mixed $payload): void
    {
        Trace::$log[] = 'async:' . ($payload['n'] ?? '?');
    }
}

class FailingListener
{
    public function handle(mixed $payload): void
    {
        throw new \RuntimeException('listener exploded');
    }
}
