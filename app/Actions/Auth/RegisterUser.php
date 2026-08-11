<?php

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\RegisterData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final readonly class RegisterUser
{
    public function handle(RegisterData $data): User
    {
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);
    }
}
