<?php

namespace App\Actions\Auth;

use App\Contracts\RefreshTokenRepositoryInterface;
use App\Exceptions\InvalidRefreshTokenException;

final readonly class RefreshAccessToken
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokens,
        private IssueTokenPair $issueTokenPair,
    ) {
    }

    public function handle(string $rawToken): array
    {
        $hash = hash('sha256', $rawToken);
        $refreshToken = $this->refreshTokens->findValidByHash($hash);

        if (! $refreshToken) {
            throw new InvalidRefreshTokenException();
        }

        // Lost the race against a concurrent refresh using the same token —
        // someone else already revoked it between findValidByHash() and here.
        if (! $this->refreshTokens->revoke($refreshToken)) {
            throw new InvalidRefreshTokenException();
        }

        $user = $refreshToken->user;

        return [
            'user' => $user,
            'tokens' => $this->issueTokenPair->handle($user),
        ];
    }
}
