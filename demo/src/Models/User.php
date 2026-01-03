<?php

declare(strict_types=1);

namespace Demo\Models;

use Authza\Interfaces\SubjectInterface;

/**
 * User model implementing SubjectInterface
 */
class User implements SubjectInterface
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private array $roles = [],
        private array $attributes = []
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roles,
            'attributes' => $this->attributes,
        ];
    }
}
