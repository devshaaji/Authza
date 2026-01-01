<?php

declare(strict_types=1);

namespace Authza\Condition\Resolvers;

use Authza\Condition\ConditionResolverInterface;
use Authza\Interfaces\SubjectInterface;
use Authza\Interfaces\ResourceInterface;

/**
 * Resolves 'owner' condition
 * 
 * Checks if the user is the owner of the resource
 */
class OwnerConditionResolver implements ConditionResolverInterface
{
    /**
     * Check if this resolver supports the condition
     *
     * @param string $condition Condition string to check
     * @return bool True if condition is 'owner'
     */
    public function supports(string $condition): bool
    {
        return $condition === 'owner';
    }

    /**
     * Evaluate owner condition
     * 
     * Returns true if the user's ID matches the resource's owner ID
     *
     * @param string $condition Condition string (should be 'owner')
     * @param SubjectInterface $user User/subject performing the action
     * @param ResourceInterface $resource Resource being accessed
     * @param array<string, mixed> $context Additional context data
     * @return bool True if user is the owner of the resource
     */
    public function evaluate(
        string $condition,
        SubjectInterface $user,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        $ownerId = $resource->getOwnerId();
        
        if ($ownerId === null) {
            return false;
        }

        // Compare user ID with owner ID (handle both string and int)
        return (string)$user->getId() === (string)$ownerId;
    }
}
