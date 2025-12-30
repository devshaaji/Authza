<?php

declare(strict_types=1);

namespace Authza\Tests\Mocks;

use Authza\Interfaces\ResourceInterface;

class MockResource implements ResourceInterface
{
    public function __construct(
        private string $resourceType,
        private string|int $resourceId,
        private string|int|null $ownerId = null,
        private array $attributes = []
    ) {
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): string|int
    {
        return $this->resourceId;
    }

    public function getOwnerId(): string|int|null
    {
        return $this->ownerId;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
