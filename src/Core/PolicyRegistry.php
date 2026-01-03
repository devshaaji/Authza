<?php

declare(strict_types=1);

namespace Authza\Core;

use Authza\DSL\PolicyDefinition;
use Authza\DSL\PolicySourceInterface;
use Authza\Interfaces\PolicyInterface;

/**
 * PolicyRegistry manages and maps resource types to their corresponding policies
 */
class PolicyRegistry
{
    /**
     * @var array<string, PolicyInterface>
     */
    private array $policies = [];

    /**
     * @var array<PolicySourceInterface>
     */
    private array $sources = [];

    /**
     * @var array<PolicyDefinition>
     */
    private array $dslPolicies = [];

    /**
     * Create a new policy registry
     *
     * @param array<string, PolicyInterface> $policies Initial policies to register
     */
    public function __construct(array $policies = [])
    {
        foreach ($policies as $resourceType => $policy) {
            $this->register($resourceType, $policy);
        }
    }

    /**
     * Register a policy for a specific resource type
     *
     * @param string $resourceType The resource type (e.g., 'user', 'invoice')
     * @param PolicyInterface $policy The policy to handle this resource type
     * @return void
     */
    public function register(string $resourceType, PolicyInterface $policy): void
    {
        $this->policies[$resourceType] = $policy;
    }

    /**
     * Get the policy for a specific resource type
     *
     * @param string $resourceType The resource type to get the policy for
     * @return PolicyInterface|null The policy or null if not found
     */
    public function get(string $resourceType): ?PolicyInterface
    {
        return $this->policies[$resourceType] ?? null;
    }

    /**
     * Register a policy source and immediately load its policies
     *
     * @param PolicySourceInterface $source The policy source to register
     * @return array<PolicyDefinition> The loaded policy definitions from this source
     */
    public function registerSource(PolicySourceInterface $source): array
    {
        $this->sources[] = $source;
        
        // Immediately load policies from the source
        $policies = $source->load();
        $this->dslPolicies = array_merge($this->dslPolicies, $policies);
        
        return $policies;
    }

    /**
     * Reload all policies from registered sources
     *
     * @return array<PolicyDefinition> All loaded DSL policy definitions
     */
    public function reloadAll(): array
    {
        $this->dslPolicies = [];

        foreach ($this->sources as $source) {
            $policies = $source->load();
            $this->dslPolicies = array_merge($this->dslPolicies, $policies);
        }

        return $this->dslPolicies;
    }

    /**
     * Get all loaded DSL policy definitions
     *
     * @return array<PolicyDefinition>
     */
    public function getDslPolicies(): array
    {
        return $this->dslPolicies;
    }
}
