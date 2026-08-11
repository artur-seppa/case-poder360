<?php

use App\Actions\Auth\RevokeAllTokens;
use App\Contracts\RefreshTokenRepositoryInterface;
use App\Models\User;

it('deletes all Sanctum access tokens and revokes all refresh tokens for the user', function () {
    $user = User::factory()->create();
    $user->createToken('token-a');
    $user->createToken('token-b');

    $refreshTokens = app(RefreshTokenRepositoryInterface::class);
    $refreshTokens->create($user, 'hash-a', now()->addDays(7));
    $refreshTokens->create($user, 'hash-b', now()->addDays(7));

    app(RevokeAllTokens::class)->handle($user);

    expect($user->tokens()->count())->toBe(0);
    expect($refreshTokens->findValidByHash('hash-a'))->toBeNull();
    expect($refreshTokens->findValidByHash('hash-b'))->toBeNull();
});

it('does not touch another user\'s tokens', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $other->createToken('other-token');

    $refreshTokens = app(RefreshTokenRepositoryInterface::class);
    $refreshTokens->create($other, 'other-hash', now()->addDays(7));

    app(RevokeAllTokens::class)->handle($user);

    expect($other->tokens()->count())->toBe(1);
    expect($refreshTokens->findValidByHash('other-hash'))->not->toBeNull();
});
