<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final readonly class UpdatePassword
{
    public function __construct(private RevokeAllTokens $revokeAllTokens)
    {
    }

    public function handle(User $user, string $password): void
    {
        $user->update([
            'password' => Hash::make($password),
        ]);

        $this->revokeAllTokens->handle($user);
    }
}
