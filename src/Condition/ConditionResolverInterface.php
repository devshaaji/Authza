<?php

declare(strict_types=1);

namespace Authza\Condition;

use Authza\Interfaces\SubjectInterface;
use Authza\Interfaces\ResourceInterface;

/**
 * Interface for runtime condition evaluation
 */
interface ConditionResolverInterface
{
    /**
     * Check if this resolver supports the given condition
     *
     * @param string $condition Condition string to check
     * @return bool True if this resolver can handle the condition
     */
    public function supports(string $condition): bool;

    /**
     * Evaluate the condition against user, resource and context
     *
     * @param string $condition Condition string to evaluate
     * @param SubjectInterface $user User/subject performing the action
     * @param ResourceInterface $resource Resource being accessed
     * @param array<string, mixed> $context Additional context data
     * @return bool True if condition is satisfied, false otherwise
     */
    public function evaluate(
        string $condition,
        SubjectInterface $user,
        ResourceInterface $resource,
        array $context = []
    ): bool;
}
