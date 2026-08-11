<?php

use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;

beforeEach(function () {
    $this->policy = new TaskPolicy();
});

it('allows the owner to view, update and delete their task', function () {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    expect($this->policy->view($owner, $task))->toBeTrue();
    expect($this->policy->update($owner, $task))->toBeTrue();
    expect($this->policy->delete($owner, $task))->toBeTrue();
});

it('denies a non-owner from viewing, updating or deleting the task', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    expect($this->policy->view($stranger, $task))->toBeFalse();
    expect($this->policy->update($stranger, $task))->toBeFalse();
    expect($this->policy->delete($stranger, $task))->toBeFalse();
});
