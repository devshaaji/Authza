<?php

declare(strict_types=1);

namespace Authza\Exceptions;

use Authza\Interfaces\SubjectInterface;
use Authza\Interfaces\ResourceInterface;
use Exception;

/**
 * AuthorizationException is thrown when authorization is denied
 */
class AuthorizationException extends Exception
{
    /**
     * Create a new authorization exception
     *
     * @param SubjectInterface $subject The subject that was denied
     * @param string $action The action that was denied
     * @param ResourceInterface $resource The resource that was being accessed
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Access denied: %s cannot %s %s:%s',
            $subject->getId(),
            $action,
            $resource->getResourceType(),
            $resource->getResourceId()
        );

        parent::__construct($message, $code, $previous);
    }
}
