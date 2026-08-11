<?php

use App\Models\Task;
use App\Models\User;

it('returns a consistent JSON shape for a 404 on the API', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks/999999');

    $response->assertStatus(404);
    $response->assertJsonStructure(['message']);
});

it('returns a consistent JSON shape for an undefined API route', function () {
    $response = $this->getJson('/api/v1/rota-que-nao-existe');

    $response->assertStatus(404);
    $response->assertExactJson(['message' => 'Recurso não encontrado.']);
});

it('returns a consistent JSON shape for a 403 on the API', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/tasks/{$task->id}");

    $response->assertStatus(403);
    $response->assertJsonStructure(['message']);
});

it('returns a consistent JSON shape for a validation error on the API', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', ['title' => '']);

    $response->assertStatus(422);
    $response->assertJsonStructure(['message', 'errors']);
});

it('returns accented characters unescaped in API JSON responses', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);

    // If unicode were escaped (ão), this literal UTF-8 substring
    // wouldn't be found in the raw response body.
    expect($response->getContent())->toContain('As credenciais informadas estão incorretas.');
});

it('does not 500 on an invalid status query param for the tasks index when JSON is expected', function () {
    // /tasks is a web route (Task 8's TaskController::index()), not an API
    // route, so it isn't covered by shouldRenderJsonWhen('api/*'). But when
    // the request itself expects JSON (Accept: application/json, as set by
    // getJson()), Illuminate\Http\Request::expectsJson() is true regardless
    // of the route, which is exactly what the ValueError render callback in
    // bootstrap/app.php checks. Verified empirically: a plain (non-JSON)
    // $this->get() to this same URL still returns 500, because it doesn't
    // satisfy expectsJson() and Task 8's TaskController is intentionally
    // left untouched — only this JSON-expecting path is fixed here.
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/tasks?status=xyz');

    $response->assertStatus(422);
    $response->assertJsonStructure(['message']);
});
