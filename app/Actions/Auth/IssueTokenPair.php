<?php

namespace App\Actions\Auth;

use App\Contracts\RefreshTokenRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class IssueTokenPair
{
    public function __construct(private RefreshTokenRepositoryInterface $refreshTokens)
    {
    }

    public function handle(User $user): array
    {
        $accessToken = $user->createToken('access-token')->plainTextToken;

        $rawRefreshToken = Str::random(64);
        $this->refreshTokens->create(
            user: $user,
            tokenHash: hash('sha256', $rawRefreshToken),
            expiresAt: now()->addDays((int) config('auth.refresh_token_ttl_days')),
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $rawRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('sanctum.expiration') * 60,
        ];
    }
}
