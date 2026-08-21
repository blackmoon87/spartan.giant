<?php

declare(strict_types=1);

namespace App\Services;

use Spartan\Application;
use Spartan\QueryBuilder;
use App\Models\Task;

class TaskService
{
    /**
     * Create a new task for a project.
     */
    public function createTask(int $projectId, string $title, string $description, int $assignedTo, string $priority = 'medium', ?string $dueDate = null): Task
    {
        $db = Application::$app->db;
        (new QueryBuilder($db, 'tasks'))->insert([
            'project_id'  => $projectId,
            'assigned_to' => $assignedTo,
            'title'       => $title,
            'description' => $description,
            'status'      => 'todo',
            'priority'    => $priority,
            'due_date'    => $dueDate,
        ]);

        $id = (int) $db->lastInsertId();
        return (new Task())->findInstance($id);
    }

    /**
     * Mark a task as completed inside an atomic transaction.
     */
    public function completeTask(int $taskId): Task
    {
        $task = new Task();
        $task->transaction(function () use ($task, $taskId) {
            $task->table()
                ->where('id', $taskId)
                ->update([
                    'status'       => 'done',
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
        });

        return $task->findInstance($taskId);
    }

    /**
     * Add a comment to a task.
     */
    public function addComment(int $taskId, int $userId, string $body): int
    {
        $db = Application::$app->db;
        (new QueryBuilder($db, 'comments'))->insert([
            'task_id' => $taskId,
            'user_id' => $userId,
            'body'    => $body,
        ]);
        return (int) $db->lastInsertId();
    }
}
