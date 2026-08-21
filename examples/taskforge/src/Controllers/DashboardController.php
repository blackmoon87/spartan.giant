<?php

declare(strict_types=1);

namespace App\Controllers;

use Spartan\Application;
use Spartan\Cache;
use Spartan\Controller;
use Spartan\QueryBuilder;
use App\Models\Project;
use App\Models\Task;

class DashboardController extends Controller
{
    public function index(): string
    {
        $db = Application::$app->db;

        // Demonstrates Cache::remember() — fetch-or-store pattern
        $stats = Cache::remember('dashboard_stats', 300, function () use ($db) {
            return [
                'total_projects'  => (new QueryBuilder($db, 'projects'))->count(),
                'total_tasks'     => (new QueryBuilder($db, 'tasks'))->count(),
                'completed_tasks' => (new QueryBuilder($db, 'tasks'))->where('status', 'done')->count(),
                'active_users'    => (new QueryBuilder($db, 'users'))->count(),
            ];
        });

        // Demonstrates QueryBuilder: join + groupBy + having + orderBy
        $topProjects = (new QueryBuilder($db, 'projects'))
            ->join('tasks', 'tasks.project_id', '=', 'projects.id')
            ->select('projects.name, projects.slug, COUNT(tasks.id) as task_count')
            ->groupBy('projects.id')
            ->having('task_count', 1, '>')
            ->orderBy('task_count', 'DESC')
            ->limit(5)
            ->get();

        // Demonstrates QueryBuilder: leftJoin
        $recentTasks = (new QueryBuilder($db, 'tasks'))
            ->leftJoin('users', 'users.id', '=', 'tasks.assigned_to')
            ->select('tasks.title, tasks.status, tasks.priority, users.name as assignee')
            ->orderBy('tasks.created_at', 'DESC')
            ->limit(5)
            ->get();

        return $this->render('dashboard/index', [
            'title'       => 'Dashboard — TaskForge',
            'stats'       => $stats,
            'topProjects' => $topProjects,
            'recentTasks' => $recentTasks,
        ]);
    }
}
