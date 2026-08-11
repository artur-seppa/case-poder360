<?php

namespace App\Actions\Task;

use App\Contracts\TaskRepositoryInterface;
use App\DataTransferObjects\Task\CreateTaskData;
use App\Models\Task;
use App\Models\User;

final readonly class CreateTask
{
    public function __construct(private TaskRepositoryInterface $tasks)
    {
    }

    public function handle(User $user, CreateTaskData $data): Task
    {
        return $this->tasks->create($user->id, [
            'title' => $data->title,
            'description' => $data->description,
            'status' => $data->status->value,
        ]);
    }
}
