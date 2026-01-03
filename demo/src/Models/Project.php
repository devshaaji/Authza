<?php

declare(strict_types=1);

namespace Demo\Models;

use Authza\Interfaces\ResourceInterface;

/**
 * Project model implementing ResourceInterface
 */
class Project implements ResourceInterface
{
    public function __construct(
        private int $id,
        private string $name,
        private int $ownerId,
        private string $status = 'active',
        private array $memberIds = [],
        private ?float $budget = null
    ) {}

    public function getResourceType(): string
    {
        return 'project';
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
            'memberIds' => $this->memberIds,
            'budget' => $this->budget,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMemberIds(): array
    {
        return $this->memberIds;
    }

    public function hasMember(int $userId): bool
    {
        return in_array($userId, $this->memberIds, true);
    }

    public function getBudget(): ?float
    {
        return $this->budget;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ownerId' => $this->ownerId,
            'status' => $this->status,
            'memberIds' => $this->memberIds,
            'budget' => $this->budget,
        ];
    }
}
