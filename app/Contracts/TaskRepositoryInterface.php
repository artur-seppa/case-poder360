<?php

namespace App\Contracts;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TaskRepositoryInterface
{
    public function paginateForUser(string $userId, ?TaskStatus $status, int $perPage = 15): LengthAwarePaginator;

    public function create(string $userId, array $attributes): Task;

    public function update(Task $task, array $attributes): Task;

    public function delete(Task $task): void;
}
