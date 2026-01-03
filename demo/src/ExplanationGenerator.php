<?php

declare(strict_types=1);

namespace Demo;

use Authza\Interfaces\ResourceInterface;
use Demo\Models\User;

/**
 * Generates explanations for authorization decisions
 */
class ExplanationGenerator
{
    /**
     * Generate a human-readable explanation for an authorization decision
     */
    public function generate(User $user, ResourceInterface $resource, string $action, bool $allowed): string
    {
        $roles = $user->getRoles();
        $isOwner = $user->getId() === $resource->getOwnerId();
        $attrs = $resource->getAttributes();
        $resourceType = $resource->getResourceType();

        $reasons = [];

        // Role-based explanations
        if (in_array('admin', $roles)) {
            $reasons[] = "✓ User has 'admin' role (full access)";
        }

        if ($isOwner) {
            $reasons[] = "✓ User owns this resource";
        }

        // Resource-specific explanations
        $reasons = array_merge($reasons, $this->getResourceExplanations(
            $resourceType, $roles, $action, $attrs, $user, $resource
        ));

        // Default explanation if none found
        if (empty($reasons)) {
            $reasons[] = $allowed 
                ? "✓ Permission granted by policy" 
                : "✗ No matching permission rule found";
        }

        // Add final verdict
        $verdict = $allowed ? "🟢 ACCESS GRANTED" : "🔴 ACCESS DENIED";
        
        return implode("\n", $reasons) . "\n\n" . $verdict;
    }

    private function getResourceExplanations(
        string $resourceType,
        array $roles,
        string $action,
        array $attrs,
        User $user,
        ResourceInterface $resource
    ): array {
        return match ($resourceType) {
            'invoice' => $this->getInvoiceExplanations($roles, $action, $attrs),
            'document' => $this->getDocumentExplanations($roles, $action, $attrs, $user, $resource),
            'project' => $this->getProjectExplanations($roles, $action, $attrs, $user, $resource),
            default => [],
        };
    }

    private function getInvoiceExplanations(array $roles, string $action, array $attrs): array
    {
        $reasons = [];
        $status = $attrs['status'] ?? '';

        if (in_array('accountant', $roles)) {
            $reasons[] = "✓ User has 'accountant' role";
        }

        if ($status === 'paid' && $action === 'edit') {
            $reasons[] = "⚠️ Invoice is PAID - editing blocked for non-admins";
        }

        if ($action === 'approve') {
            if (in_array('manager', $roles)) {
                $reasons[] = "✓ User has 'manager' role (can approve)";
            } else {
                $reasons[] = "✗ Only managers can approve invoices";
            }
        }

        if (in_array('intern', $roles) && $action === 'delete') {
            $reasons[] = "✗ Interns cannot delete invoices (DSL deny rule)";
        }

        return $reasons;
    }

    private function getDocumentExplanations(
        array $roles, 
        string $action, 
        array $attrs,
        User $user,
        ResourceInterface $resource
    ): array {
        $reasons = [];
        $isConfidential = $attrs['confidential'] ?? false;
        $isOwner = $user->getId() === $resource->getOwnerId();

        if ($isConfidential) {
            if (!$isOwner && !in_array('admin', $roles)) {
                $reasons[] = "⚠️ Document is CONFIDENTIAL - restricted to owner/admin";
            } else {
                $reasons[] = "🔒 Confidential document - access granted (owner/admin)";
            }
        }

        if (in_array('employee', $roles)) {
            $reasons[] = "✓ User has 'employee' role";
        }

        if (in_array('editor', $roles) && in_array($action, ['edit', 'view'])) {
            $reasons[] = "✓ User has 'editor' role";
        }

        if ($action === 'share' && !$isOwner && !in_array('admin', $roles) && !in_array('manager', $roles)) {
            $reasons[] = "✗ Only owners, admins, or managers can share documents";
        }

        return $reasons;
    }

    private function getProjectExplanations(
        array $roles,
        string $action,
        array $attrs,
        User $user,
        ResourceInterface $resource
    ): array {
        $reasons = [];
        $status = $attrs['status'] ?? 'active';
        $memberIds = $attrs['memberIds'] ?? [];
        $budget = $attrs['budget'] ?? null;
        $isMember = in_array($user->getId(), $memberIds, true);
        $isOwner = $user->getId() === $resource->getOwnerId();

        if ($isMember && !$isOwner) {
            $reasons[] = "✓ User is a project member";
        }

        if ($status === 'archived') {
            $reasons[] = "📦 Project is ARCHIVED - limited modifications allowed";
        }

        if ($action === 'edit' && $status === 'archived' && !in_array('admin', $roles)) {
            $reasons[] = "✗ Cannot edit archived projects (admin only)";
        }

        if ($action === 'delete' && !in_array($status, ['draft', 'cancelled'])) {
            $reasons[] = "⚠️ Can only delete draft/cancelled projects";
        }

        if ($action === 'manage_budget' && $budget !== null && $budget > 100000) {
            $reasons[] = "💰 Large budget (>\$100k) - admin approval required";
        }

        if (in_array('manager', $roles)) {
            $reasons[] = "✓ User has 'manager' role";
        }

        if (in_array('intern', $roles)) {
            $reasons[] = "✗ Interns have no project access (DSL deny rule)";
        }

        return $reasons;
    }
}
