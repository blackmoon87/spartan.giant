<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\StoreProjectRequest;
use Spartan\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Services\ProjectService;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projectService)
    {
        parent::__construct();
    }

    public function index(): string
    {
        $projects = (new Project())->all();

        // Demonstrates eager loading (loadFor) — prevents N+1
        $tasks = (new Task())->tasks ?? [];
        $projectInstances = array_map(fn($p) => (new Project())->findInstance($p['id']), $projects);
        $projectsWithTasks = (new Task())->project()->loadFor($projects);

        return $this->render('projects/index', [
            'title'    => 'Projects — TaskForge',
            'projects' => $projects,
        ]);
    }

    public function show(string $slug): string
    {
        $project = (new Project())->findInstanceBy('slug', $slug);
        if (!$project) {
            $this->response->setStatusCode(404);
            return $this->render('error_404', ['title' => '404 — Not Found']);
        }

        $tasks = $project->tasks()->for($project);
        $stats = $this->projectService->getStats((int)$project->id);

        return $this->render('projects/show', [
            'title'   => $project->name . ' — TaskForge',
            'project' => $project,
            'tasks'   => $tasks,
            'stats'   => $stats,
        ]);
    }

    public function store(StoreProjectRequest $request): void
    {
        $userId = (int)$this->session->get('user_id', 1);
        $project = $this->projectService->createProject(
            $userId,
            (string)$request->post('name'),
            (string)$request->post('description'),
            (string)$request->post('priority'),
            $request->post('deadline') ?: null,
        );

        $this->session->setFlash('success', "Project '{$project->name}' created!");
        $this->redirect('/projects');
    }
}
