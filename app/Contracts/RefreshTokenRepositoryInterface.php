<?php

namespace App\Contracts;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\CarbonInterface;

interface RefreshTokenRepositoryInterface
{
    public function create(User $user, string $tokenHash, CarbonInterface $expiresAt): RefreshToken;

    public function findValidByHash(string $tokenHash): ?RefreshToken;

    /**
     * Revokes the token, but only if it hasn't been revoked already.
     *
     * @return bool True if this call actually revoked it; false if it was
     *              already revoked (e.g. by a concurrent refresh request).
     */
    public function revoke(RefreshToken $refreshToken): bool;

    /**
     * Deletes expired tokens (revoked or not — once expired, neither state
     * is useful anymore). Keeps the table from growing unbounded.
     *
     * @return int Number of rows deleted.
     */
    public function pruneExpired(): int;

    /**
     * Revokes every active (not yet revoked) refresh token for the user.
     *
     * @return int Number of rows revoked.
     */
    public function revokeAllForUser(User $user): int;
}
