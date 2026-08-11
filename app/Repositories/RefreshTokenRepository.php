<?php

namespace App\Repositories;

use App\Contracts\RefreshTokenRepositoryInterface;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\CarbonInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function create(User $user, string $tokenHash, CarbonInterface $expiresAt): RefreshToken
    {
        return $user->refreshTokens()->create([
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findValidByHash(string $tokenHash): ?RefreshToken
    {
        return RefreshToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function revoke(RefreshToken $refreshToken): bool
    {
        // Atomic conditional update instead of check-then-write: if two
        // requests race on the same token, only the one that actually
        // flips revoked_at (affects a row) wins; the other gets 0 rows
        // affected and knows it lost the race.
        return (bool) RefreshToken::query()
            ->whereKey($refreshToken->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function pruneExpired(): int
    {
        return RefreshToken::query()
            ->where('expires_at', '<', now())
            ->delete();
    }

    public function revokeAllForUser(User $user): int
    {
        return RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
