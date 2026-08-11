<?php

namespace App\DataTransferObjects\Task;

use App\Enums\TaskStatus;

final readonly class CreateTaskData
{
    public function __construct(
        public string $title,
        public ?string $description,
        public TaskStatus $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'] ?? null,
            status: isset($data['status']) ? TaskStatus::from($data['status']) : TaskStatus::Pendente,
        );
    }
}
