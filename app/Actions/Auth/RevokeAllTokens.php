<?php

namespace App\Actions\Auth;

use App\Contracts\RefreshTokenRepositoryInterface;
use App\Models\User;

final readonly class RevokeAllTokens
{
    public function __construct(private RefreshTokenRepositoryInterface $refreshTokens)
    {
    }

    /**
     * Revokes every API credential the user has — Sanctum access tokens and
     * custom refresh tokens, no exceptions. Meant to run right after a
     * password change (profile update or reset), since a stolen access/
     * refresh token is a separate credential from the password and survives
     * a password change on its own otherwise.
     */
    public function handle(User $user): void
    {
        $user->tokens()->delete();
        $this->refreshTokens->revokeAllForUser($user);
    }
}
