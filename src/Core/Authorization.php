<?php

declare(strict_types=1);

namespace Authza\Core;

use Authza\Core\Graph\PermissionGraph;
use Authza\Exceptions\AuthorizationException;
use Authza\Interfaces\ResourceInterface;
use Authza\Interfaces\SubjectInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Authorization is the main entry point for authorization checks
 */
class Authorization
{
    private const CACHE_TTL = 300;

    private PolicyRegistry $registry;
    private ?CacheInterface $cache;
    private ?LoggerInterface $logger;
    private ?PermissionGraph $graph;

    /**
     * Create a new authorization instance
     *
     * @param PolicyRegistry $registry Policy registry for resource type mappings
     * @param CacheInterface|null $cache Optional PSR-16 cache for decision caching
     * @param LoggerInterface|null $logger Optional PSR-3 logger for audit logging
     * @param PermissionGraph|null $graph Optional permission graph for precomputed decisions
     */
    public function __construct(
        PolicyRegistry $registry,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?PermissionGraph $graph = null
    ) {
        $this->registry = $registry;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->graph = $graph;
    }

    /**
     * Check if a subject can perform an action on a resource
     *
     * @param SubjectInterface $subject The subject attempting the action
     * @param string $action The action being performed
     * @param ResourceInterface $resource The resource being accessed
     * @param array<string, mixed> $context Additional context for the decision
     * @return bool True if allowed, false otherwise
     */
    public function can(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        $subjectId = (string)$subject->getId();
        $resourceType = $resource->getResourceType();
        $resourceId = (string)$resource->getResourceId();

        // Check permission graph first (fastest)
        if ($this->graph !== null) {
            // Build list of subject identifiers with proper prefixes:
            // - user:<id> for the user's ID
            // - role:<name> for each role
            $subjectIds = ['user:' . $subjectId];
            foreach ($subject->getRoles() as $role) {
                $subjectIds[] = 'role:' . $role;
            }
            
            // Single optimized call checks all subjects with proper deny precedence
            $graphResult = $this->graph->checkMultiple($subjectIds, $action, $resourceType, $resourceId);
            
            if ($graphResult !== null) {
                $this->log($graphResult, $subject, $action, $resource, $context, 'graph');
                return $graphResult;
            }
        }

        // Evaluate policy
        $policy = $this->registry->get($resourceType);
        
        if ($policy === null) {
            $this->log(false, $subject, $action, $resource, $context, 'no-policy');
            return false;
        }

        $allowed = $policy->can($subject, $action, $resource, $context);

        $this->log($allowed, $subject, $action, $resource, $context, 'policy');

        return $allowed;
    }

    /**
     * Authorize an action or throw an exception if denied
     *
     * @param SubjectInterface $subject The subject attempting the action
     * @param string $action The action being performed
     * @param ResourceInterface $resource The resource being accessed
     * @param array<string, mixed> $context Additional context for the decision
     * @return void
     * @throws AuthorizationException If authorization is denied
     */
    public function authorize(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): void {
        if (!$this->can($subject, $action, $resource, $context)) {
            throw new AuthorizationException($subject, $action, $resource);
        }
    }

    /**
     * Quick start factory method with sensible defaults
     *
     * @param array<string, mixed> $config Configuration options
     * @return self
     */
    public static function quickStart(array $config = []): self
    {
        $cache = $config['cache'] ?? null;
        $logger = $config['logger'] ?? null;
        
        $graph = null;
        if ($cache !== null) {
            $graph = new PermissionGraph($cache);
        }

        $registry = new PolicyRegistry();

        // Register explicit policies if provided
        if (isset($config['policies']) && is_array($config['policies'])) {
            foreach ($config['policies'] as $resourceType => $policy) {
                $registry->register($resourceType, $policy);
            }
        }

        return new self($registry, $cache, $logger, $graph);
    }

    /**
     * Log an authorization decision
     *
     * @param bool $result The authorization result
     * @param SubjectInterface $subject The subject
     * @param string $action The action
     * @param ResourceInterface $resource The resource
     * @param array<string, mixed> $context The context
     * @param string $source The source of the decision (graph, cache, policy, no-policy)
     * @return void
     */
    private function log(
        bool $result,
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context,
        string $source
    ): void {
        if ($this->logger === null) {
            return;
        }

        $logContext = [
            'subject_id' => $subject->getId(),
            'action' => $action,
            'resource_type' => $resource->getResourceType(),
            'resource_id' => $resource->getResourceId(),
            'result' => $result ? 'allowed' : 'denied',
            'source' => $source,
            'context' => $context,
        ];

        if ($result) {
            $this->logger->info('Authorization check: allowed', $logContext);
        } else {
            $this->logger->warning('Authorization check: denied', $logContext);
        }
    }
}
