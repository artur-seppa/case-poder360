<?php

it('rejects registration with a password shorter than 8 characters', function () {
    $response = $this->post('/register', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'short1',
        'password_confirmation' => 'short1',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
});

it('accepts registration with a password of exactly 8 characters', function () {
    $response = $this->post('/register', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('tasks.index', absolute: false));
    $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
});
