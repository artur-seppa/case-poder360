<?php

namespace App\DataTransferObjects\Task;

use App\Enums\TaskStatus;

final readonly class UpdateTaskData
{
    public function __construct(
        public ?string $title,
        public ?string $description,
        public bool $descriptionProvided,
        public ?TaskStatus $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionProvided: array_key_exists('description', $data),
            status: isset($data['status']) ? TaskStatus::from($data['status']) : null,
        );
    }

    public function toAttributes(): array
    {
        $attributes = array_filter([
            'title' => $this->title,
            'status' => $this->status?->value,
        ], fn ($value) => $value !== null);

        // description is nullable/clearable, so its presence (not its
        // null-ness) decides whether it's part of a partial update — a
        // plain array_filter would drop an explicit "clear it" the same
        // way it drops "field wasn't sent at all".
        if ($this->descriptionProvided) {
            $attributes['description'] = $this->description;
        }

        return $attributes;
    }
}
