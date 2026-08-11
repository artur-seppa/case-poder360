<?php

namespace App\Repositories;

use App\Contracts\TaskRepositoryInterface;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaskRepository implements TaskRepositoryInterface
{
    public function paginateForUser(string $userId, ?TaskStatus $status, int $perPage = 15): LengthAwarePaginator
    {
        return Task::query()
            ->where('user_id', $userId)
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(string $userId, array $attributes): Task
    {
        return Task::create([...$attributes, 'user_id' => $userId]);
    }

    public function update(Task $task, array $attributes): Task
    {
        $task->update($attributes);

        return $task->fresh();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }
}
