<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

function authenticatedApiUser(): User
{
    $user = User::factory()->create();
    test()->actingAs($user, 'sanctum');

    return $user;
}

it('lists only the authenticated user tasks via API', function () {
    $user = authenticatedApiUser();
    Task::factory()->for($user)->create(['title' => 'Minha tarefa']);
    Task::factory()->create(['title' => 'De outro usuário']);

    $response = $this->getJson('/api/v1/tasks');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['title' => 'Minha tarefa']);
});

it('paginates tasks via API', function () {
    $user = authenticatedApiUser();
    Task::factory()->for($user)->count(20)->create();

    $firstPage = $this->getJson('/api/v1/tasks');

    $firstPage->assertOk();
    $firstPage->assertJsonCount(15, 'data');
    $firstPage->assertJsonPath('meta.current_page', 1);
    $firstPage->assertJsonPath('meta.last_page', 2);
    $firstPage->assertJsonPath('meta.per_page', 15);
    $firstPage->assertJsonPath('meta.total', 20);

    $secondPage = $this->getJson('/api/v1/tasks?page=2');

    $secondPage->assertOk();
    $secondPage->assertJsonCount(5, 'data');
    $secondPage->assertJsonPath('meta.current_page', 2);
});

it('filters tasks by status via API', function () {
    $user = authenticatedApiUser();
    Task::factory()->for($user)->create(['status' => TaskStatus::Pendente->value]);
    Task::factory()->for($user)->create(['status' => TaskStatus::Concluida->value]);

    $response = $this->getJson('/api/v1/tasks?'.http_build_query(['status' => TaskStatus::Concluida->value]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

it('resets the status filter when an empty status is submitted via API', function () {
    $user = authenticatedApiUser();
    Task::factory()->for($user)->create();

    $response = $this->getJson('/api/v1/tasks?status=');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

it('creates a task via API', function () {
    $user = authenticatedApiUser();

    $response = $this->postJson('/api/v1/tasks', [
        'title' => 'Nova tarefa API',
        'status' => TaskStatus::Pendente->value,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('tasks', ['title' => 'Nova tarefa API', 'user_id' => $user->id]);
});

it('validates required fields on create', function () {
    authenticatedApiUser();

    $response = $this->postJson('/api/v1/tasks', ['title' => '']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('title');
});

it('shows a single task via API', function () {
    $user = authenticatedApiUser();
    $task = Task::factory()->for($user)->create();

    $response = $this->getJson("/api/v1/tasks/{$task->id}");

    $response->assertOk();
    $response->assertJsonFragment(['id' => $task->id]);
});

it('updates a task via API', function () {
    $user = authenticatedApiUser();
    $task = Task::factory()->for($user)->create(['title' => 'Antigo']);

    $response = $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Atualizado']);

    $response->assertOk();
    expect($task->fresh()->title)->toBe('Atualizado');
});

it('deletes a task via API', function () {
    $user = authenticatedApiUser();
    $task = Task::factory()->for($user)->create();

    $response = $this->deleteJson("/api/v1/tasks/{$task->id}");

    $response->assertNoContent();
    expect(Task::find($task->id))->toBeNull();
});

it('returns 403 when accessing another user task via API', function () {
    authenticatedApiUser();
    $task = Task::factory()->create();

    $this->getJson("/api/v1/tasks/{$task->id}")->assertForbidden();
    $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Hackeado'])->assertForbidden();
    $this->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/v1/tasks')->assertUnauthorized();
});

it('rate limits the tasks endpoint at 60 requests per minute', function () {
    authenticatedApiUser();

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/tasks')->assertOk();
    }

    $this->getJson('/api/v1/tasks')->assertStatus(429);
});
