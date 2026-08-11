<?php

use App\Contracts\TaskRepositoryInterface;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->repository = app(TaskRepositoryInterface::class);
});

it('paginates only the tasks belonging to the given user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Task::factory()->for($owner)->count(2)->create();
    Task::factory()->for($other)->count(3)->create();

    $result = $this->repository->paginateForUser($owner->id, null);

    expect($result->total())->toBe(2);
});

it('filters by status when provided', function () {
    $owner = User::factory()->create();
    Task::factory()->for($owner)->create(['status' => TaskStatus::Pendente->value]);
    Task::factory()->for($owner)->create(['status' => TaskStatus::Concluida->value]);

    $result = $this->repository->paginateForUser($owner->id, TaskStatus::Concluida);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(TaskStatus::Concluida);
});

it('creates a task for a user', function () {
    $user = User::factory()->create();

    $task = $this->repository->create($user->id, [
        'title' => 'Nova tarefa',
        'description' => null,
        'status' => TaskStatus::Pendente->value,
    ]);

    expect($task->exists)->toBeTrue();
    expect($task->user_id)->toBe($user->id);
});

it('updates a task', function () {
    $task = Task::factory()->create(['title' => 'Antigo']);

    $updated = $this->repository->update($task, ['title' => 'Novo']);

    expect($updated->title)->toBe('Novo');
    expect($task->fresh()->title)->toBe('Novo');
});

it('deletes a task', function () {
    $task = Task::factory()->create();

    $this->repository->delete($task);

    expect(Task::find($task->id))->toBeNull();
});
