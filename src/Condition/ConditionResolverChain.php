<?php

declare(strict_types=1);

namespace Authza\Condition;

use Authza\Interfaces\SubjectInterface;
use Authza\Interfaces\ResourceInterface;

/**
 * Chains multiple condition resolvers
 * 
 * Tries each resolver in order until one supports the condition
 */
class ConditionResolverChain implements ConditionResolverInterface
{
    /**
     * @var array<ConditionResolverInterface>
     */
    private array $resolvers = [];

    /**
     * Create a new condition resolver chain
     *
     * @param array<ConditionResolverInterface> $resolvers Array of resolvers
     */
    public function __construct(array $resolvers = [])
    {
        foreach ($resolvers as $resolver) {
            $this->addResolver($resolver);
        }
    }

    /**
     * Add a resolver to the chain
     *
     * @param ConditionResolverInterface $resolver Resolver to add
     * @return void
     */
    public function addResolver(ConditionResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * Check if any resolver in the chain supports the condition
     *
     * @param string $condition Condition string to check
     * @return bool True if any resolver supports the condition
     */
    public function supports(string $condition): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($condition)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate the condition using the first resolver that supports it
     *
     * @param string $condition Condition string to evaluate
     * @param SubjectInterface $user User/subject performing the action
     * @param ResourceInterface $resource Resource being accessed
     * @param array<string, mixed> $context Additional context data
     * @return bool True if condition is satisfied, false otherwise
     * @throws \RuntimeException If no resolver supports the condition
     */
    public function evaluate(
        string $condition,
        SubjectInterface $user,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($condition)) {
                return $resolver->evaluate($condition, $user, $resource, $context);
            }
        }

        throw new \RuntimeException(
            "No resolver found for condition: {$condition}"
        );
    }
}
