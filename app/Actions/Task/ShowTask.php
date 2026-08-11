<?php

namespace App\Actions\Task;

use App\Models\Task;

final readonly class ShowTask
{
    public function handle(Task $task): Task
    {
        return $task;
    }
}
