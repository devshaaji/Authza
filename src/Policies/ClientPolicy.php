<?php

declare(strict_types=1);

namespace Authza\Policies;

use Authza\Interfaces\PolicyInterface;
use Authza\Interfaces\ResourceInterface;
use Authza\Interfaces\SubjectInterface;

/**
 * ClientPolicy handles authorization rules for client resources
 */
class ClientPolicy implements PolicyInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $resourceType): bool
    {
        return $resourceType === 'client';
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
        $ownerId = $resource->getOwnerId();

        return match ($action) {
            'view' => in_array('admin', $roles) 
                || in_array('sales', $roles)
                || $subjectId == $ownerId,
            'edit' => in_array('admin', $roles) || in_array('sales', $roles),
            'delete' => in_array('admin', $roles),
            default => false,
        };
    }
}
