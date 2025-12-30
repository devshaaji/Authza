<?php

declare(strict_types=1);

namespace Authza\Tests\Mocks;

use Authza\Interfaces\SubjectInterface;

class MockSubject implements SubjectInterface
{
    public function __construct(
        private string|int $id,
        private array $roles = [],
        private array $attributes = []
    ) {
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
