<?php

declare(strict_types=1);

namespace Authza;

use Authza\Interfaces\SubjectInterface;
use Authza\Interfaces\ResourceInterface;
use Authza\Exceptions\AuthorizationException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Authorization
{
    private ?CacheItemPoolInterface $cache = null;
    private LoggerInterface $logger;

    public function __construct(?CacheItemPoolInterface $cache = null, ?LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function can(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        $this->logger->debug('Checking permission', [
            'subject' => $subject->getId(),
            'action' => $action,
            'resource' => $resource->getType() . ':' . ($resource->getId() ?? '*')
        ]);

        return false;
    }

    /**
     * @param array<string, mixed> $context
     * @throws AuthorizationException
     */
    public function authorize(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): void {
        if (!$this->can($subject, $action, $resource, $context)) {
            throw new AuthorizationException(
                "Access denied for {$action} on {$resource->getType()}:{$resource->getId()}"
            );
        }
    }
}
