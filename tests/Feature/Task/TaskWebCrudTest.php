<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

it('lists only the authenticated user tasks', function () {
    $user = User::factory()->create();
    $ownTask = Task::factory()->for($user)->create(['title' => 'Minha tarefa']);
    Task::factory()->create(['title' => 'Tarefa de outro usuário']);

    $response = $this->actingAs($user)->get(route('tasks.index'));

    $response->assertOk();
    $response->assertSee('Minha tarefa');
    $response->assertDontSee('Tarefa de outro usuário');
});

it('shows the create form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('tasks.create'))->assertOk();
});

it('creates a task', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tasks.store'), [
        'title' => 'Nova tarefa',
        'description' => 'Descrição',
        'status' => TaskStatus::Pendente->value,
    ]);

    $response->assertRedirect(route('tasks.index'));
    $this->assertDatabaseHas('tasks', ['title' => 'Nova tarefa', 'user_id' => $user->id]);
});

it('fails to create a task without a title', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tasks.store'), ['title' => '']);

    $response->assertSessionHasErrors('title');
});

it('shows a task detail page', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $response = $this->actingAs($user)->get(route('tasks.show', $task));

    $response->assertOk();
    $response->assertSee($task->title);
});

it('shows the edit form for own task', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $this->actingAs($user)->get(route('tasks.edit', $task))->assertOk();
});

it('updates a task', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['title' => 'Antigo']);

    $response = $this->actingAs($user)->put(route('tasks.update', $task), [
        'title' => 'Atualizado',
    ]);

    $response->assertRedirect(route('tasks.index'));
    expect($task->fresh()->title)->toBe('Atualizado');
});

it('deletes a task', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $response = $this->actingAs($user)->delete(route('tasks.destroy', $task));

    $response->assertRedirect(route('tasks.index'));
    expect(Task::find($task->id))->toBeNull();
});

it('forbids a user from viewing another user task', function () {
    $stranger = User::factory()->create();
    $task = Task::factory()->create();

    $this->actingAs($stranger)->get(route('tasks.show', $task))->assertForbidden();
});

it('forbids a user from viewing another user task edit form', function () {
    $stranger = User::factory()->create();
    $task = Task::factory()->create();

    $this->actingAs($stranger)->get(route('tasks.edit', $task))->assertForbidden();
});

it('forbids a user from updating another user task', function () {
    $stranger = User::factory()->create();
    $task = Task::factory()->create(['title' => 'Original']);

    $this->actingAs($stranger)->put(route('tasks.update', $task), ['title' => 'Hackeado'])
        ->assertForbidden();

    expect($task->fresh()->title)->toBe('Original');
});

it('forbids a user from deleting another user task', function () {
    $stranger = User::factory()->create();
    $task = Task::factory()->create();

    $this->actingAs($stranger)->delete(route('tasks.destroy', $task))->assertForbidden();

    expect(Task::find($task->id))->not->toBeNull();
});

it('rejects an invalid status parameter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('tasks.index', ['status' => 'xyz']));

    $response->assertSessionHasErrors('status');
});

it('resets the status filter when an empty status is submitted', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('tasks.index', ['status' => '']));

    $response->assertOk();
    $response->assertSessionDoesntHaveErrors('status');
});

it('redirects guests to the login page', function () {
    $this->get(route('tasks.index'))->assertRedirect(route('login'));
});

it('rate limits the tasks routes at 60 requests per minute', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    for ($i = 0; $i < 60; $i++) {
        $this->get(route('tasks.index'))->assertOk();
    }

    $this->get(route('tasks.index'))->assertStatus(429);
});
