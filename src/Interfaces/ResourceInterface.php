<?php

declare(strict_types=1);

namespace Authza\Interfaces;

interface ResourceInterface
{
    public function getType(): string;

    public function getId(): string|int|null;

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array;
}
