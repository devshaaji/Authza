<?php

declare(strict_types=1);

namespace Authza\Interfaces;

/**
 * SubjectInterface represents an entity (user, service, etc.) performing an action
 */
interface SubjectInterface
{
    /**
     * Get the unique identifier of the subject
     *
     * @return string|int Subject identifier
     */
    public function getId(): string|int;

    /**
     * Get the roles assigned to the subject
     *
     * @return array<string> Array of role names
     */
    public function getRoles(): array;

    /**
     * Get additional attributes for attribute-based access control
     *
     * @return array<string, mixed> Key-value pairs of subject attributes
     */
    public function getAttributes(): array;
}
