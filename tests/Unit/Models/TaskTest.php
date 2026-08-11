<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

it('belongs to a user and casts status to the TaskStatus enum', function () {
    $user = User::factory()->create();

    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::EmAndamento->value,
    ]);

    expect($task->user)->toBeInstanceOf(User::class);
    expect($task->user->id)->toBe($user->id);
    expect($task->status)->toBeInstanceOf(TaskStatus::class);
    expect($task->status)->toBe(TaskStatus::EmAndamento);
});

it('lists tasks through the user relation', function () {
    $user = User::factory()->create();
    Task::factory()->for($user)->count(3)->create();

    expect($user->tasks()->count())->toBe(3);
});
