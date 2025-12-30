<?php

declare(strict_types=1);

namespace Authza\Interfaces;

interface SubjectInterface
{
    public function getId(): string|int;

    /**
     * @return array<string>
     */
    public function getRoles(): array;

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array;
}
