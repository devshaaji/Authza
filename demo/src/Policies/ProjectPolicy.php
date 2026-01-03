<?php

declare(strict_types=1);

namespace Demo\Policies;

use Authza\Interfaces\PolicyInterface;
use Authza\Interfaces\ResourceInterface;
use Authza\Interfaces\SubjectInterface;

/**
 * ProjectPolicy - demonstrates team-based and status-aware permissions
 */
class ProjectPolicy implements PolicyInterface
{
    public function supports(string $resourceType): bool
    {
        return $resourceType === 'project';
    }

    public function can(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        $roles = $subject->getRoles();
        $userId = $subject->getId();
        $isOwner = $userId === $resource->getOwnerId();
        $attrs = $resource->getAttributes();
        $isMember = in_array($userId, $attrs['memberIds'] ?? [], true);
        $status = $attrs['status'] ?? 'active';

        return match ($action) {
            'view' => $this->canView($roles, $isOwner, $isMember),
            'edit' => $this->canEdit($roles, $isOwner, $isMember, $status),
            'delete' => $this->canDelete($roles, $isOwner, $status),
            'archive' => $this->canArchive($roles, $isOwner),
            'add_member' => $this->canAddMember($roles, $isOwner),
            'remove_member' => $this->canRemoveMember($roles, $isOwner),
            'manage_budget' => $this->canManageBudget($roles, $isOwner, $attrs['budget'] ?? null),
            default => false,
        };
    }

    private function canView(array $roles, bool $isOwner, bool $isMember): bool
    {
        // Admins, managers, owners, and members can view
        return in_array('admin', $roles) 
            || in_array('manager', $roles) 
            || $isOwner 
            || $isMember;
    }

    private function canEdit(array $roles, bool $isOwner, bool $isMember, string $status): bool
    {
        // Cannot edit archived projects
        if ($status === 'archived') {
            return in_array('admin', $roles);
        }

        return in_array('admin', $roles) || $isOwner || $isMember;
    }

    private function canDelete(array $roles, bool $isOwner, string $status): bool
    {
        // Can only delete draft or cancelled projects
        if (!in_array($status, ['draft', 'cancelled'])) {
            return in_array('admin', $roles);
        }

        return in_array('admin', $roles) || $isOwner;
    }

    private function canArchive(array $roles, bool $isOwner): bool
    {
        return in_array('admin', $roles) || in_array('manager', $roles) || $isOwner;
    }

    private function canAddMember(array $roles, bool $isOwner): bool
    {
        return in_array('admin', $roles) || in_array('manager', $roles) || $isOwner;
    }

    private function canRemoveMember(array $roles, bool $isOwner): bool
    {
        return in_array('admin', $roles) || $isOwner;
    }

    private function canManageBudget(array $roles, bool $isOwner, ?float $budget): bool
    {
        // Only admins can manage large budgets (over 100k)
        if ($budget !== null && $budget > 100000) {
            return in_array('admin', $roles);
        }

        return in_array('admin', $roles) || in_array('manager', $roles) || $isOwner;
    }
}
