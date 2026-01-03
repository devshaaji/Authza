<?php

declare(strict_types=1);

namespace Demo\Models;

use Authza\Interfaces\ResourceInterface;

/**
 * Invoice model implementing ResourceInterface
 */
class Invoice implements ResourceInterface
{
    public function __construct(
        private int $id,
        private string $title,
        private float $amount,
        private int $ownerId,
        private string $status = 'draft',
        private string $department = 'general'
    ) {}

    public function getResourceType(): string
    {
        return 'invoice';
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
            'status' => $this->status,
            'department' => $this->department,
            'amount' => $this->amount,
        ];
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getStatus(): string
    {
        return $this->status;
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
            'amount' => $this->amount,
            'ownerId' => $this->ownerId,
            'status' => $this->status,
            'department' => $this->department,
        ];
    }
}
