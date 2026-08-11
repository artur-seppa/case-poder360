<?php

namespace App\Actions\Task;

use App\Contracts\TaskRepositoryInterface;
use App\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListTasks
{
    public function __construct(private TaskRepositoryInterface $tasks)
    {
    }

    public function handle(User $user, ?TaskStatus $status): LengthAwarePaginator
    {
        return $this->tasks->paginateForUser($user->id, $status);
    }
}
