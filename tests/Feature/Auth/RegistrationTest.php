<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('tasks.index', absolute: false));
});

test('rejects registration with an already-taken email', function () {
    $existing = User::factory()->create();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => $existing->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->assertSame(1, User::where('email', $existing->email)->count());
});

test('registration is rate limited at 5 requests per minute', function () {
    // Uses an already-taken email so every attempt fails validation and never
    // authenticates — otherwise the first successful register would log the
    // user in, and the `guest` middleware would redirect (not throttle) every
    // attempt after that.
    User::factory()->create(['email' => 'ratelimit@example.com']);

    $payload = [
        'name' => 'Test User',
        'email' => 'ratelimit@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->post('/register', $payload);
    }

    $this->post('/register', $payload)->assertStatus(429);
});
