<?php

use App\Actions\Task\CreateTask;
use App\Actions\Task\DeleteTask;
use App\Actions\Task\ListTasks;
use App\Actions\Task\ShowTask;
use App\Actions\Task\UpdateTask;
use App\DataTransferObjects\Task\CreateTaskData;
use App\DataTransferObjects\Task\UpdateTaskData;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

it('creates a task owned by the given user', function () {
    $user = User::factory()->create();
    $data = CreateTaskData::fromArray(['title' => 'Estudar Laravel', 'status' => TaskStatus::Pendente->value]);

    $task = app(CreateTask::class)->handle($user, $data);

    expect($task->title)->toBe('Estudar Laravel');
    expect($task->user_id)->toBe($user->id);
});

it('lists only the tasks for the given user, optionally filtered by status', function () {
    $user = User::factory()->create();
    Task::factory()->for($user)->create(['status' => TaskStatus::Pendente->value]);
    Task::factory()->for($user)->create(['status' => TaskStatus::Concluida->value]);
    Task::factory()->create(); // outro usuário

    $all = app(ListTasks::class)->handle($user, null);
    $onlyConcluida = app(ListTasks::class)->handle($user, TaskStatus::Concluida);

    expect($all->total())->toBe(2);
    expect($onlyConcluida->total())->toBe(1);
});

it('shows a task', function () {
    $task = Task::factory()->create();

    $result = app(ShowTask::class)->handle($task);

    expect($result->id)->toBe($task->id);
});

it('updates only the provided fields of a task', function () {
    $task = Task::factory()->create(['title' => 'Original', 'status' => TaskStatus::Pendente->value]);
    $data = UpdateTaskData::fromArray(['status' => TaskStatus::EmAndamento->value]);

    $updated = app(UpdateTask::class)->handle($task, $data);

    expect($updated->title)->toBe('Original');
    expect($updated->status)->toBe(TaskStatus::EmAndamento);
});

it('leaves the description untouched when the field is not sent at all', function () {
    $task = Task::factory()->create(['description' => 'Original']);
    $data = UpdateTaskData::fromArray(['title' => 'Novo título']);

    $updated = app(UpdateTask::class)->handle($task, $data);

    expect($updated->description)->toBe('Original');
});

it('clears the description when it is explicitly sent as empty', function () {
    $task = Task::factory()->create(['description' => 'Original']);
    $data = UpdateTaskData::fromArray(['title' => 'Novo título', 'description' => null]);

    $updated = app(UpdateTask::class)->handle($task, $data);

    expect($updated->description)->toBeNull();
});

it('deletes a task', function () {
    $task = Task::factory()->create();

    app(DeleteTask::class)->handle($task);

    expect(Task::find($task->id))->toBeNull();
});
