<?php

declare(strict_types=1);

namespace Authza\Interfaces;

/**
 * ResourceInterface represents a resource being accessed
 */
interface ResourceInterface
{
    /**
     * Get the type of the resource (e.g., 'invoice', 'user', 'client')
     *
     * @return string Resource type identifier
     */
    public function getResourceType(): string;

    /**
     * Get the unique identifier of the resource
     *
     * @return string|int Resource identifier
     */
    public function getResourceId(): string|int;

    /**
     * Get the owner ID of the resource (if applicable)
     *
     * @return string|int|null Owner identifier or null if no owner
     */
    public function getOwnerId(): string|int|null;

    /**
     * Get additional attributes for attribute-based access control
     *
     * @return array<string, mixed> Key-value pairs of resource attributes
     */
    public function getAttributes(): array;
}
