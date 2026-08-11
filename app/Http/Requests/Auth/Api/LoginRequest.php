<?php

namespace App\Http\Requests\Auth\Api;

use App\DataTransferObjects\Auth\LoginData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }

    public function toDto(): LoginData
    {
        return new LoginData(
            email: $this->validated('email'),
            password: $this->validated('password'),
        );
    }
}
