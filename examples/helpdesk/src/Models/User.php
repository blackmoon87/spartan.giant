<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

/**
 * @property int|string  $id
 * @property string      $name
 * @property string      $email
 * @property string      $password
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class User extends Model
{
    use \Spartan\Traits\HasAuthorization;    // hasRole(), hasPermission(), assignRole()

    protected string $table = 'users';
    protected bool $timestamps = true;

    /** Roles a user can hold; exactly one per user in this app. */
    public const ROLES = ['admin', 'agent', 'customer'];

    public function tickets(): RelationQuery
    {
        return $this->hasMany(Ticket::class, foreignKey: 'user_id');
    }

    /**
     * Every user with their role slug, for the admin screen.
     *
     * @return list<array{id:int|string, name:string, email:string, created_at:?string, role:?string}>
     */
    public function listWithRoles(): array
    {
        return $this->table()
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'roles.slug as role')
            ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->leftJoin('roles', 'roles.id', '=', 'user_roles.role_id')
            ->orderBy('users.id')
            ->get();
    }

    /** Replace the user's role with $slug (one role per user). */
    public function setRole(int $userId, string $slug): void
    {
        $this->transaction(function () use ($userId, $slug): void {
            $this->table('user_roles')->where('user_id', $userId)->delete();
            (new self())->findInstance($userId)?->assignRole($slug);
        });
    }

    /**
     * Agents and admins: the people tickets can be assigned to.
     *
     * @return array<int|string, string> id => name
     */
    public function staff(): array
    {
        // Not pluck(): it replaces the SELECT with bare "name, id", which is
        // ambiguous here because roles has a name column too.
        $rows = $this->table()
            ->select('users.id', 'users.name')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.slug', ['admin', 'agent'])
            ->orderBy('users.name')
            ->get();
        return array_column($rows, 'name', 'id');
    }

    /** Ids of every agent and admin, for "new ticket" notifications. */
    public function staffIds(): array
    {
        return array_map('intval', array_keys($this->staff()));
    }
}
