<?php

namespace App\Actions\Auth;

use App\Contracts\RefreshTokenRepositoryInterface;
use App\Models\User;

final readonly class LogoutUser
{
    public function __construct(private RefreshTokenRepositoryInterface $refreshTokens)
    {
    }

    public function handle(User $user, ?string $rawRefreshToken): void
    {
        $user->currentAccessToken()->delete();

        if ($rawRefreshToken) {
            $refreshToken = $this->refreshTokens->findValidByHash(hash('sha256', $rawRefreshToken));

            // Only revoke it if it actually belongs to the authenticated user —
            // otherwise a mismatched refresh_token cookie (e.g. sent alongside
            // someone else's Bearer token) could revoke a different user's session.
            if ($refreshToken && $refreshToken->user_id === $user->id) {
                $this->refreshTokens->revoke($refreshToken);
            }
        }
    }
}
