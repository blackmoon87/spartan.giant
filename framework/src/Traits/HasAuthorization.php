<?php

declare(strict_types=1);

namespace Spartan\Traits;

use Spartan\Application;
use Spartan\QueryBuilder;

trait HasAuthorization
{
    /**
     * Cache user roles and permissions in-memory to prevent duplicate queries on the same request.
     */
    protected ?array $_cachedRoles = null;
    protected ?array $_cachedPermissions = null;

    /**
     * Check if the user has one of the specified roles.
     */
    public function hasRole(string ...$roleSlugs): bool
    {
        $roles = $this->getRoles();
        foreach ($roleSlugs as $slug) {
            if (in_array($slug, $roles, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the user has a specific permission via any of their roles.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        $permissions = $this->getPermissions();
        return in_array($permissionSlug, $permissions, true);
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(string $roleSlug): void
    {
        $db = Application::$app->db;
        if (!$db) {
            return;
        }

        $role = (new QueryBuilder($db, 'roles'))->where('slug', $roleSlug)->first();
        if (!$role) {
            throw new \InvalidArgumentException("Role [{$roleSlug}] does not exist.");
        }

        $exists = (new QueryBuilder($db, 'user_roles'))
            ->where('user_id', $this->id)
            ->where('role_id', $role['id'])
            ->first();

        if (!$exists) {
            (new QueryBuilder($db, 'user_roles'))->insert([
                'user_id' => $this->id,
                'role_id' => $role['id']
            ]);
        }

        $this->_cachedRoles = null;
        $this->_cachedPermissions = null;
    }

    /**
     * Retrieve all role slugs for this user.
     */
    public function getRoles(): array
    {
        if ($this->_cachedRoles !== null) {
            return $this->_cachedRoles;
        }

        $db = Application::$app->db;
        if (!$db || !isset($this->id)) {
            return [];
        }

        $rows = (new QueryBuilder($db, 'roles'))
            ->join('user_roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $this->id)
            ->select('roles.slug')
            ->get();

        $this->_cachedRoles = array_column($rows, 'slug');
        return $this->_cachedRoles;
    }

    /**
     * Retrieve all permission slugs for this user via their roles.
     */
    public function getPermissions(): array
    {
        if ($this->_cachedPermissions !== null) {
            return $this->_cachedPermissions;
        }

        $db = Application::$app->db;
        if (!$db || !isset($this->id)) {
            return [];
        }

        $rows = (new QueryBuilder($db, 'permissions'))
            ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->where('user_roles.user_id', $this->id)
            ->select('permissions.slug')
            ->get();

        $this->_cachedPermissions = array_unique(array_column($rows, 'slug'));
        return $this->_cachedPermissions;
    }
}
