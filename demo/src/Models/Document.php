<?php

declare(strict_types=1);

namespace Demo\Models;

use Authza\Interfaces\ResourceInterface;

/**
 * Document model implementing ResourceInterface
 */
class Document implements ResourceInterface
{
    public function __construct(
        private int $id,
        private string $title,
        private int $ownerId,
        private bool $confidential = false,
        private string $department = 'general'
    ) {}

    public function getResourceType(): string
    {
        return 'document';
    }

    public function getResourceId(): int
    {
        return $this->id;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function getAttributes(): array
    {
        return [
            'confidential' => $this->confidential,
            'department' => $this->department,
        ];
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isConfidential(): bool
    {
        return $this->confidential;
    }

    public function getDepartment(): string
    {
        return $this->department;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'ownerId' => $this->ownerId,
            'confidential' => $this->confidential,
            'department' => $this->department,
        ];
    }
}
