<?php

declare(strict_types=1);

namespace Authza\Interfaces;

interface PolicyInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function can(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): bool;
}
