<?php

declare(strict_types=1);

namespace App\Services;

use Spartan\Application;
use Spartan\QueryBuilder;
use App\Models\Project;

class ProjectService
{
    /**
     * Create a new project with a URL-safe slug.
     */
    public function createProject(int $userId, string $name, string $description, string $priority = 'medium', ?string $deadline = null): Project
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        $db = Application::$app->db;
        (new QueryBuilder($db, 'projects'))->insert([
            'user_id'     => $userId,
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'status'      => 'active',
            'priority'    => $priority,
            'deadline'    => $deadline,
        ]);

        $id = (int) $db->lastInsertId();
        return (new Project())->findInstance($id);
    }

    /**
     * Get project statistics: total tasks, completed, in-progress.
     */
    public function getStats(int $projectId): array
    {
        $db = Application::$app->db;
        $total      = (new QueryBuilder($db, 'tasks'))->where('project_id', $projectId)->count();
        $done       = (new QueryBuilder($db, 'tasks'))->where('project_id', $projectId)->where('status', 'done')->count();
        $inProgress = (new QueryBuilder($db, 'tasks'))->where('project_id', $projectId)->where('status', 'in_progress')->count();

        return [
            'total'       => $total,
            'done'        => $done,
            'in_progress' => $inProgress,
            'todo'        => $total - $done - $inProgress,
            'completion'  => $total > 0 ? round(($done / $total) * 100) : 0,
        ];
    }
}
