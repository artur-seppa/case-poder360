<?php

namespace App\Actions\Task;

use App\Contracts\TaskRepositoryInterface;
use App\DataTransferObjects\Task\UpdateTaskData;
use App\Models\Task;

final readonly class UpdateTask
{
    public function __construct(private TaskRepositoryInterface $tasks)
    {
    }

    public function handle(Task $task, UpdateTaskData $data): Task
    {
        return $this->tasks->update($task, $data->toAttributes());
    }
}
