<?php

declare(strict_types=1);

namespace Authza\Match;

use Authza\Interfaces\ResourceInterface;

/**
 * Matches DSL resource patterns against resources
 * 
 * Handles patterns like:
 * - invoice (matches any invoice resource)
 * - invoice:123 (matches invoice with ID 123)
 * - invoice:* (matches any invoice resource)
 */
class ResourceMatcher
{
    /**
     * Check if a resource matches the resource pattern
     *
     * @param string $pattern Resource pattern from DSL (e.g., invoice, invoice:123, invoice:*)
     * @param ResourceInterface $resource Resource to match against
     * @return bool True if resource matches the pattern
     */
    public function matches(string $pattern, ResourceInterface $resource): bool
    {
        // Parse the pattern
        $parts = explode(':', $pattern, 2);
        $resourceType = $parts[0];
        $resourceId = $parts[1] ?? null;

        // Check if resource type matches
        if ($resource->getResourceType() !== $resourceType) {
            return false;
        }

        // If no specific ID in pattern, it matches all resources of this type
        if ($resourceId === null) {
            return true;
        }

        // Wildcard matches any resource of this type
        if ($resourceId === '*') {
            return true;
        }

        // Compare resource IDs (handle both string and int)
        return (string)$resource->getResourceId() === $resourceId;
    }
}
