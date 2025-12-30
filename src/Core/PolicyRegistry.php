<?php

declare(strict_types=1);

namespace Authza\Core;

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
     * Auto-discover policies in a directory
     *
     * @param string $namespace The namespace for the policies
     * @param string $directory The directory to scan for policy files
     * @return void
     */
    public function autoDiscover(string $namespace, string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*Policy.php');
        
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fullClassName = rtrim($namespace, '\\') . '\\' . $className;

            if (!class_exists($fullClassName)) {
                continue;
            }

            $policy = new $fullClassName();

            if (!$policy instanceof PolicyInterface) {
                continue;
            }

            // Try to infer resource type from class name
            // e.g., UserPolicy -> user, InvoicePolicy -> invoice
            $resourceType = strtolower(str_replace('Policy', '', $className));
            
            if ($policy->supports($resourceType)) {
                $this->register($resourceType, $policy);
            }
        }
    }
}
