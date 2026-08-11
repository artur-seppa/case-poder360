<?php

use App\Contracts\RefreshTokenRepositoryInterface;
use App\Models\User;

beforeEach(function () {
    $this->repository = app(RefreshTokenRepositoryInterface::class);
});

it('creates and finds a valid refresh token by hash', function () {
    $user = User::factory()->create();

    $token = $this->repository->create($user, 'hash-123', now()->addDays(7));

    $found = $this->repository->findValidByHash('hash-123');

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($token->id);
});

it('does not find an expired token', function () {
    $user = User::factory()->create();
    $this->repository->create($user, 'expired-hash', now()->subDay());

    expect($this->repository->findValidByHash('expired-hash'))->toBeNull();
});

it('does not find a revoked token', function () {
    $user = User::factory()->create();
    $token = $this->repository->create($user, 'revoked-hash', now()->addDays(7));

    $this->repository->revoke($token);

    expect($this->repository->findValidByHash('revoked-hash'))->toBeNull();
});

it('prunes expired tokens but keeps valid ones', function () {
    $user = User::factory()->create();
    $this->repository->create($user, 'expired-1', now()->subDay());
    $this->repository->create($user, 'expired-2', now()->subMinute());
    $this->repository->create($user, 'still-valid', now()->addDays(7));

    $deleted = $this->repository->pruneExpired();

    expect($deleted)->toBe(2);
    expect($this->repository->findValidByHash('still-valid'))->not->toBeNull();
    expect(App\Models\RefreshToken::where('token_hash', 'expired-1')->exists())->toBeFalse();
    expect(App\Models\RefreshToken::where('token_hash', 'expired-2')->exists())->toBeFalse();
});

it('revoke is atomic: only the first of two concurrent calls on the same token succeeds', function () {
    $user = User::factory()->create();
    $token = $this->repository->create($user, 'race-hash', now()->addDays(7));

    $first = $this->repository->revoke($token);
    $second = $this->repository->revoke($token);

    expect($first)->toBeTrue();
    expect($second)->toBeFalse();
});
