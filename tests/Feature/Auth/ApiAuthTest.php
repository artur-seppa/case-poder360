<?php

use App\Models\Task;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('requires a valid Bearer token even when the client also has an active web session', function () {
    // config/sanctum.php's `guard` is `[]` on purpose: Sanctum's guard checks
    // that list of guards (default `['web']`) before the Bearer token, so
    // a request carrying both a valid web session cookie AND an invalid/
    // revoked API token would otherwise silently authenticate via the
    // session, never actually checking the token at all.
    $user = User::factory()->create(['password' => bcrypt('old-password')]);
    $accessToken = $user->createToken('access-token')->plainTextToken;

    $this->post('/login', ['email' => $user->email, 'password' => 'old-password']);
    $this->assertAuthenticated();

    // Revoke the token directly (equivalent to "it's now invalid for any
    // reason"), while the web session above stays active — exactly what a
    // password change does today via RevokeAllTokens.
    $user->tokens()->delete();

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/tasks')
        ->assertUnauthorized();
});

it('deletes the authenticated user\'s account, cascading their tasks', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);
    $accessToken = $user->createToken('access-token')->plainTextToken;
    Task::factory()->for($user)->count(3)->create();

    $response = $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->deleteJson('/api/v1/user', ['password' => 'password123']);

    $response->assertNoContent();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('tasks', ['user_id' => $user->id]);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects account deletion via API with the wrong password', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);
    $accessToken = $user->createToken('access-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->deleteJson('/api/v1/user', ['password' => 'wrong-password']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('password');
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('rejects unauthenticated account deletion requests', function () {
    $this->deleteJson('/api/v1/user', ['password' => 'irrelevant'])
        ->assertUnauthorized();
});

it('updates the password via API and revokes all existing tokens', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);
    $accessToken = $user->createToken('access-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->putJson('/api/v1/user/password', [
            'current_password' => 'old-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

    $response->assertNoContent();
    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();

    // Sanctum's RequestGuard caches its resolved user for the guard
    // instance's lifetime, and this TestCase reuses one guard instance
    // across the two calls in this test — a real request would always
    // re-resolve fresh, so this reset only matters here.
    $this->app['auth']->forgetGuards();

    // The token used for THIS request is gone too — revokes everything.
    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/tasks')
        ->assertUnauthorized();
});

it('rejects a password update via API with the wrong current password', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);
    $accessToken = $user->createToken('access-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->putJson('/api/v1/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('current_password');
    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('sends a password reset link via API', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('rejects a password reset link request via API for an unknown email', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('resets the password via API with a valid token and revokes existing tokens', function () {
    Notification::fake();

    $user = User::factory()->create();
    $accessToken = $user->createToken('access-token')->plainTextToken;

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        return true;
    });

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/tasks')
        ->assertUnauthorized();
});

it('rejects a password reset via API with an invalid token', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('registers a new user, returns an access token, and sets a refresh token cookie', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['user', 'access_token', 'token_type', 'expires_in']);
    $response->assertJsonMissingPath('refresh_token');
    $response->assertCookie('refresh_token');
    $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
});

it('rejects a non-lowercase email on registration via API', function () {
    // 'lowercase' is a rejecting validation rule, not a normalizer — it
    // forces the client to submit lowercase rather than silently fixing it.
    // Matches the web RegisterRequest, so the DB's case-sensitive unique
    // index on email can't be bypassed by casing (e.g. Ana@x.com vs ana@x.com).
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana',
        'email' => 'Ana@Example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
    $this->assertDatabaseMissing('users', ['email' => 'Ana@Example.com']);
});

it('rejects registration via API with an already-taken email', function () {
    $existing = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana',
        'email' => $existing->email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
    $this->assertSame(1, User::where('email', $existing->email)->count());
});

it('reports a non-zero token expiration', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk();
    expect($response->json('expires_in'))->toBeGreaterThan(0);
});

it('defaults the Sanctum token expiration to 15 minutes in config, not just at the call site', function () {
    // config('key', $default) does NOT fall back to $default when the key
    // exists but resolves to null — which is what env('SANCTUM_EXPIRATION')
    // gives if that var is unset. The default has to live inside the env()
    // call in config/sanctum.php itself, or IssueTokenPair silently reports
    // expires_in: 0 on any deployment that omits that one env var.
    putenv('SANCTUM_EXPIRATION');
    unset($_ENV['SANCTUM_EXPIRATION'], $_SERVER['SANCTUM_EXPIRATION']);

    expect((require config_path('sanctum.php'))['expiration'])->toBe(15);
});

it('rejects registration via API with a password shorter than 8 characters', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'short1',
        'password_confirmation' => 'short1',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('password');
    $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
});

it('logs in with valid credentials, returns an access token, and sets a refresh token cookie', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['user', 'access_token', 'token_type', 'expires_in']);
    $response->assertJsonMissingPath('refresh_token');
    $response->assertCookie('refresh_token');
});

it('rejects login with invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
});

it('rejects a login password shorter than 8 characters as a validation error, not wrong credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'short1',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('password');
});

it('rotates the refresh token cookie on refresh, invalidating the old one', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);
    $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password123']);
    $originalRefreshToken = $login->getCookie('refresh_token', false)->getValue();

    $refreshResponse = $this->withUnencryptedCookie('refresh_token', $originalRefreshToken)
        ->withCredentials()
        ->postJson('/api/v1/auth/refresh');
    $refreshResponse->assertOk();
    $refreshResponse->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    $refreshResponse->assertJsonMissingPath('refresh_token');
    $refreshResponse->assertCookie('refresh_token');
    $newRefreshToken = $refreshResponse->getCookie('refresh_token', false)->getValue();
    expect($newRefreshToken)->not->toBe($originalRefreshToken);

    $reuseResponse = $this->withUnencryptedCookie('refresh_token', $originalRefreshToken)
        ->withCredentials()
        ->postJson('/api/v1/auth/refresh');
    $reuseResponse->assertStatus(401);
});

it('rejects a refresh request with no refresh token cookie', function () {
    $response = $this->postJson('/api/v1/auth/refresh');

    $response->assertStatus(422);
});

it('rejects an unknown refresh token cookie', function () {
    $response = $this->withUnencryptedCookie('refresh_token', 'nao-existe')
        ->withCredentials()
        ->postJson('/api/v1/auth/refresh');

    $response->assertStatus(401);
});

it('does not revoke another user\'s refresh token on logout', function () {
    $victim = User::factory()->create(['password' => bcrypt('password123')]);
    $victimLogin = $this->postJson('/api/v1/auth/login', ['email' => $victim->email, 'password' => 'password123']);
    $victimRefreshToken = $victimLogin->getCookie('refresh_token', false)->getValue();

    $attacker = User::factory()->create();
    $attackerAccessToken = $attacker->createToken('access-token')->plainTextToken;

    // Attacker is authenticated as themselves, but the refresh_token cookie
    // on this request happens to be the victim's (e.g. a stray/forged cookie).
    $this->withHeader('Authorization', "Bearer {$attackerAccessToken}")
        ->withUnencryptedCookie('refresh_token', $victimRefreshToken)
        ->withCredentials()
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseHas('refresh_tokens', [
        'token_hash' => hash('sha256', $victimRefreshToken),
        'revoked_at' => null,
    ]);
});

it('logs out, revokes the current access token, and clears the refresh token cookie', function () {
    $user = User::factory()->create();
    $accessToken = $user->createToken('access-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->postJson('/api/v1/auth/logout');

    $response->assertNoContent();
    $response->assertCookieExpired('refresh_token');

    // The `sanctum` guard caches its resolved user for the lifetime of the
    // guard instance, and this TestCase reuses one container (and thus one
    // guard instance) across the two postJson() calls in this test — a real
    // request always boots a fresh container, so this reset only matters here.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/tasks')
        ->assertUnauthorized();
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();
    $accessToken = $user->createToken('access-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/user');

    $response->assertOk();
    $response->assertJsonPath('id', $user->id);
    $response->assertJsonPath('email', $user->email);
});

it('rejects an unauthenticated request for the current user', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});

it('rate limits the login endpoint at 5 requests per minute', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(429);
});
