<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Spartan\Application;
use Spartan\Controller;
use Spartan\QueryBuilder;

/**
 * Stateless REST API controller — demonstrates JSON responses and QueryBuilder pagination.
 */
class TaskApiController extends Controller
{
    public function index(): void
    {
        $db = Application::$app->db;
        $page    = max(1, (int)$this->request->get('page', '1'));
        $perPage = max(1, min(50, (int)$this->request->get('per_page', '10')));

        // Demonstrates QueryBuilder::paginate()
        $result = (new QueryBuilder($db, 'tasks'))
            ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
            ->leftJoin('users', 'users.id', '=', 'tasks.assigned_to')
            ->select('tasks.id, tasks.title, tasks.status, tasks.priority, projects.name as project, users.name as assignee')
            ->orderBy('tasks.created_at', 'DESC')
            ->paginate($perPage, $page);

        $this->json($result);
    }

    public function show(string $id): void
    {
        $db = Application::$app->db;
        $task = (new QueryBuilder($db, 'tasks'))->find((int)$id);

        if (!$task) {
            $this->json(['error' => 'Task not found'], 404);
            return;
        }

        // Demonstrates QueryBuilder::where()->get() for related comments
        $comments = (new QueryBuilder($db, 'comments'))
            ->leftJoin('users', 'users.id', '=', 'comments.user_id')
            ->select('comments.body, users.name as author, comments.created_at')
            ->where('comments.task_id', (int)$id)
            ->get();

        $task['comments'] = $comments;
        $this->json(['data' => $task]);
    }
}
