<?php

declare(strict_types=1);

namespace Authza\Interfaces;

/**
 * PolicyInterface defines authorization rules for a specific resource type
 */
interface PolicyInterface
{
    /**
     * Check if this policy supports the given resource type
     *
     * @param string $resourceType The resource type to check
     * @return bool True if this policy handles the resource type
     */
    public function supports(string $resourceType): bool;

    /**
     * Evaluate if the subject can perform the action on the resource
     *
     * @param SubjectInterface $subject The subject attempting the action
     * @param string $action The action being performed (e.g., 'view', 'edit', 'delete')
     * @param ResourceInterface $resource The resource being accessed
     * @param array<string, mixed> $context Additional context for decision-making
     * @return bool True if the action is allowed, false otherwise
     */
    public function can(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): bool;
}
