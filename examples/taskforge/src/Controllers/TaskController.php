<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\StoreTaskRequest;
use Spartan\Controller;
use App\Models\Task;
use App\Services\TaskService;

class TaskController extends Controller
{
    public function __construct(private TaskService $taskService)
    {
        parent::__construct();
    }

    public function store(StoreTaskRequest $request): void
    {
        $task = $this->taskService->createTask(
            (int)$request->post('project_id'),
            (string)$request->post('title'),
            (string)$request->post('description', ''),
            (int)$request->post('assigned_to'),
            (string)$request->post('priority', 'medium'),
            $request->post('due_date') ?: null,
        );

        $this->session->setFlash('success', "Task '{$task->title}' created!");
        $this->redirect('/project/' . ($request->post('project_slug') ?? 'spartan-core'));
    }

    /**
     * Complete a task — demonstrates method spoofing (PUT via _method).
     */
    public function complete(string $id): void
    {
        $task = $this->taskService->completeTask((int)$id);

        // Dispatch event with sync + async listeners
        $this->event('task.completed', [
            'id'          => $task->id,
            'title'       => $task->title,
            'assigned_to' => $task->assigned_to,
            'project_id'  => $task->project_id,
        ]);

        $this->session->setFlash('success', "Task '{$task->title}' marked as done!");
        $this->redirect('/dashboard');
    }

    /**
     * HTMX partial rendering — returns raw HTML without layout wrapper.
     */
    public function partialRow(string $id): string
    {
        $task = (new Task())->findInstance((int)$id);
        return $this->renderViewOnly('tasks/partials/task_row', ['task' => $task ? $task->toArray() : []]);
    }
}
