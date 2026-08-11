<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(TaskStatus::class)],
        ];
    }

    public function status(): ?TaskStatus
    {
        return $this->filled('status')
            ? TaskStatus::from($this->string('status')->toString())
            : null;
    }
}
