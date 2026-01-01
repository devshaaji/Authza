<?php

declare(strict_types=1);

namespace Authza\Condition\Resolvers;

use Authza\Condition\ConditionResolverInterface;
use Authza\Interfaces\SubjectInterface;
use Authza\Interfaces\ResourceInterface;

/**
 * Resolves attribute-based conditions
 * 
 * Handles patterns like:
 * - key==value (equality check)
 * - key!=value (inequality check)
 */
class AttributeConditionResolver implements ConditionResolverInterface
{
    /**
     * Check if this resolver supports the condition
     *
     * @param string $condition Condition string to check
     * @return bool True if condition matches key==value or key!=value pattern
     */
    public function supports(string $condition): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(!?==?).+$/', $condition) === 1;
    }

    /**
     * Evaluate attribute condition
     * 
     * Checks user or resource attributes against the condition
     *
     * @param string $condition Condition string (e.g., department==finance)
     * @param SubjectInterface $user User/subject performing the action
     * @param ResourceInterface $resource Resource being accessed
     * @param array<string, mixed> $context Additional context data
     * @return bool True if condition is satisfied
     */
    public function evaluate(
        string $condition,
        SubjectInterface $user,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        // Parse the condition
        if (str_contains($condition, '!=')) {
            [$key, $expectedValue] = array_map('trim', explode('!=', $condition, 2));
            $operator = '!=';
        } elseif (str_contains($condition, '==')) {
            [$key, $expectedValue] = array_map('trim', explode('==', $condition, 2));
            $operator = '==';
        } else {
            return false;
        }

        // Get actual value from user attributes, resource attributes, or context
        $actualValue = $this->getAttributeValue($key, $user, $resource, $context);

        // Evaluate based on operator
        if ($operator === '==') {
            return (string)$actualValue === $expectedValue;
        } else { // !=
            return (string)$actualValue !== $expectedValue;
        }
    }

    /**
     * Get attribute value from user, resource, or context
     *
     * @param string $key Attribute key
     * @param SubjectInterface $user User/subject
     * @param ResourceInterface $resource Resource
     * @param array<string, mixed> $context Context
     * @return mixed Attribute value or null if not found
     */
    private function getAttributeValue(
        string $key,
        SubjectInterface $user,
        ResourceInterface $resource,
        array $context
    ): mixed {
        // Check context first
        if (array_key_exists($key, $context)) {
            return $context[$key];
        }

        // Check user attributes
        $userAttributes = $user->getAttributes();
        if (array_key_exists($key, $userAttributes)) {
            return $userAttributes[$key];
        }

        // Check resource attributes
        $resourceAttributes = $resource->getAttributes();
        if (array_key_exists($key, $resourceAttributes)) {
            return $resourceAttributes[$key];
        }

        return null;
    }
}
