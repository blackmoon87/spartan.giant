<?php

declare(strict_types=1);

namespace App\Controllers;

use Spartan\Attributes\RequirePermission;
use Spartan\Attributes\RequireRole;
use Spartan\Controller;
use Spartan\Application;
use Spartan\QueryBuilder;

/**
 * Admin-only controller — demonstrates #[RequireRole] and #[RequirePermission] attributes.
 * Only users with 'admin' role AND 'manage_users' permission can access this controller.
 */
#[RequireRole('admin')]
#[RequirePermission('manage_users')]
class AdminController extends Controller
{
    public function index(): string
    {
        $db = Application::$app->db;

        $users = (new QueryBuilder($db, 'users'))
            ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->leftJoin('roles', 'roles.id', '=', 'user_roles.role_id')
            ->select('users.id, users.name, users.email, roles.name as role_name')
            ->get();

        return $this->render('dashboard/index', [
            'title' => 'Admin Panel — TaskForge',
            'stats' => [
                'total_projects'  => (new QueryBuilder($db, 'projects'))->count(),
                'total_tasks'     => (new QueryBuilder($db, 'tasks'))->count(),
                'completed_tasks' => (new QueryBuilder($db, 'tasks'))->where('status', 'done')->count(),
                'active_users'    => (new QueryBuilder($db, 'users'))->count(),
            ],
            'topProjects' => [],
            'recentTasks' => [],
        ]);
    }
}
