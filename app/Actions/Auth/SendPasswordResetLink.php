<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

final readonly class SendPasswordResetLink
{
    public function handle(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }
}
