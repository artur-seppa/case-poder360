<?php

use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
});

test('updating the password logs out other sessions', function () {
    Event::fake([OtherDeviceLogout::class]);

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    Event::assertDispatched(OtherDeviceLogout::class, fn ($event) => $event->user->is($user));
});

test('updating the password revokes all existing API tokens', function () {
    $user = User::factory()->create();
    $accessToken = $user->createToken('access-token')->plainTextToken;

    $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    // Sanctum's guard checks the `web` session guard before the Bearer token
    // (`config('sanctum.guard')`, for first-party SPA support) — without
    // forgetting it, this test's `actingAs()` web session would leak into
    // the API call below and short-circuit past the token check entirely.
    // A real request here would never have both at once.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/tasks')
        ->assertUnauthorized();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});
