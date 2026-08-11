<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('resetting the password revokes all existing API tokens', function () {
    Notification::fake();

    $user = User::factory()->create();
    $accessToken = $user->createToken('access-token')->plainTextToken;

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        return true;
    });

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/tasks')
        ->assertUnauthorized();
});

test('reset password link request fails silently for an unknown email', function () {
    $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

    $response->assertSessionHasErrors('email');
});

test('password cannot be reset with an invalid token', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);

    $response = $this->post('/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Illuminate\Support\Facades\Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('reset password link request is rate limited at 5 requests per minute', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post('/forgot-password', ['email' => $user->email]);
    }

    $this->post('/forgot-password', ['email' => $user->email])->assertStatus(429);
});
