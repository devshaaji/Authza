<?php

declare(strict_types=1);

namespace Demo\Policies;

use Authza\Interfaces\PolicyInterface;
use Authza\Interfaces\ResourceInterface;
use Authza\Interfaces\SubjectInterface;

/**
 * DocumentPolicy - demonstrates confidentiality and ownership rules
 */
class DocumentPolicy implements PolicyInterface
{
    public function supports(string $resourceType): bool
    {
        return $resourceType === 'document';
    }

    public function can(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        $roles = $subject->getRoles();
        $isOwner = $subject->getId() === $resource->getOwnerId();
        $isConfidential = $resource->getAttributes()['confidential'] ?? false;

        return match ($action) {
            'view' => $this->canView($roles, $isOwner, $isConfidential),
            'edit' => $this->canEdit($roles, $isOwner),
            'delete' => $this->canDelete($roles, $isOwner),
            'share' => $this->canShare($roles, $isOwner),
            'download' => $this->canDownload($roles, $isOwner, $isConfidential),
            default => false,
        };
    }

    private function canView(array $roles, bool $isOwner, bool $isConfidential): bool
    {
        // Admins can view everything
        if (in_array('admin', $roles)) {
            return true;
        }
        
        // Confidential docs only for owners
        if ($isConfidential && !$isOwner) {
            return false;
        }
        
        // Owners and employees can view non-confidential
        return $isOwner || in_array('employee', $roles) || in_array('manager', $roles);
    }

    private function canEdit(array $roles, bool $isOwner): bool
    {
        return $isOwner || in_array('admin', $roles) || in_array('editor', $roles);
    }

    private function canDelete(array $roles, bool $isOwner): bool
    {
        // Only admins or owners can delete
        return in_array('admin', $roles) || $isOwner;
    }

    private function canShare(array $roles, bool $isOwner): bool
    {
        return $isOwner || in_array('admin', $roles) || in_array('manager', $roles);
    }

    private function canDownload(array $roles, bool $isOwner, bool $isConfidential): bool
    {
        if (in_array('admin', $roles)) {
            return true;
        }
        
        if ($isConfidential) {
            return $isOwner;
        }
        
        return $isOwner || in_array('employee', $roles);
    }
}
