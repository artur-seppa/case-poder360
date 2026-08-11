<?php

namespace App\Http\Requests\Auth\Api;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['refresh_token' => $this->cookie('refresh_token')]);
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }
}
