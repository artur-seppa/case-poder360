<?php

namespace App\Http\Requests\Task;

use App\DataTransferObjects\Task\CreateTaskData;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
        ];
    }

    public function toDto(): CreateTaskData
    {
        return CreateTaskData::fromArray($this->validated());
    }
}
