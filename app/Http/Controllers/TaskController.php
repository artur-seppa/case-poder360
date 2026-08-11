<?php

namespace App\Http\Controllers;

use App\Actions\Task\CreateTask;
use App\Actions\Task\DeleteTask;
use App\Actions\Task\ListTasks;
use App\Actions\Task\ShowTask;
use App\Actions\Task\UpdateTask;
use App\Enums\TaskStatus;
use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ListTasks $listTasks,
        private readonly CreateTask $createTask,
        private readonly ShowTask $showTask,
        private readonly UpdateTask $updateTask,
        private readonly DeleteTask $deleteTask,
    ) {
    }

    public function index(IndexTaskRequest $request): View
    {
        $status = $request->status();

        return view('tasks.index', [
            'tasks' => $this->listTasks->handle($request->user(), $status),
            'statuses' => TaskStatus::cases(),
            'selectedStatus' => $status,
        ]);
    }

    public function create(): View
    {
        return view('tasks.create', ['statuses' => TaskStatus::cases()]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $this->createTask->handle($request->user(), $request->toDto());

        return redirect()->route('tasks.index')->with('status', 'Tarefa criada com sucesso.');
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);

        return view('tasks.show', ['task' => $this->showTask->handle($task)]);
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);

        return view('tasks.edit', ['task' => $task, 'statuses' => TaskStatus::cases()]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->updateTask->handle($task, $request->toDto());

        return redirect()->route('tasks.index')->with('status', 'Tarefa atualizada com sucesso.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $this->deleteTask->handle($task);

        return redirect()->route('tasks.index')->with('status', 'Tarefa removida com sucesso.');
    }
}
