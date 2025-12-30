<?php

declare(strict_types=1);

namespace Authza\Policies;

use Authza\Interfaces\PolicyInterface;
use Authza\Interfaces\ResourceInterface;
use Authza\Interfaces\SubjectInterface;

/**
 * InvoicePolicy handles authorization rules for invoice resources
 */
class InvoicePolicy implements PolicyInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $resourceType): bool
    {
        return $resourceType === 'invoice';
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
            'view' => $this->canView($roles, $subjectId, $ownerId),
            'edit' => $this->canEdit($roles, $context),
            'delete' => in_array('admin', $roles),
            'approve' => $this->canApprove($roles),
            default => false,
        };
    }

    /**
     * Check if subject can view the invoice
     */
    private function canView(array $roles, string|int $subjectId, string|int|null $ownerId): bool
    {
        return in_array('admin', $roles) 
            || in_array('accountant', $roles)
            || $subjectId == $ownerId;
    }

    /**
     * Check if subject can edit the invoice
     */
    private function canEdit(array $roles, array $context): bool
    {
        $hasRole = in_array('admin', $roles) || in_array('accountant', $roles);
        
        if (!$hasRole) {
            return false;
        }

        // Invoice must not be paid
        $status = $context['status'] ?? null;
        return $status !== 'paid';
    }

    /**
     * Check if subject can approve the invoice
     */
    private function canApprove(array $roles): bool
    {
        return in_array('admin', $roles) || in_array('manager', $roles);
    }
}
