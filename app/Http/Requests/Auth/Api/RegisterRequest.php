<?php

namespace App\Http\Requests\Auth\Api;

use App\DataTransferObjects\Auth\RegisterData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function toDto(): RegisterData
    {
        return new RegisterData(
            name: $this->validated('name'),
            email: $this->validated('email'),
            password: $this->validated('password'),
        );
    }
}
