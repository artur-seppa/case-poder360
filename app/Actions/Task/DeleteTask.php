<?php

namespace App\Actions\Task;

use App\Contracts\TaskRepositoryInterface;
use App\Models\Task;

final readonly class DeleteTask
{
    public function __construct(private TaskRepositoryInterface $tasks)
    {
    }

    public function handle(Task $task): void
    {
        $this->tasks->delete($task);
    }
}
