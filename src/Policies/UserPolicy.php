<?php

declare(strict_types=1);

namespace Authza\Policies;

use Authza\Interfaces\PolicyInterface;
use Authza\Interfaces\ResourceInterface;
use Authza\Interfaces\SubjectInterface;

/**
 * UserPolicy handles authorization rules for user resources
 */
class UserPolicy implements PolicyInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $resourceType): bool
    {
        return $resourceType === 'user';
    }

    /**
     * @inheritDoc
     */
    public function can(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        $roles = $subject->getRoles();
        $subjectId = $subject->getId();
        $resourceId = $resource->getResourceId();

        return match ($action) {
            'view', 'edit' => in_array('admin', $roles) || $subjectId == $resourceId,
            'delete' => in_array('admin', $roles),
            default => false,
        };
    }
}
