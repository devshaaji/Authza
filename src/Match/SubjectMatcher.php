<?php

declare(strict_types=1);

namespace Authza\Match;

use Authza\Interfaces\SubjectInterface;

/**
 * Matches DSL subject patterns against users
 * 
 * Handles patterns like:
 * - role:admin (matches users with admin role)
 * - user:42 (matches user with ID 42)
 * - user:* (matches any user)
 */
class SubjectMatcher
{
    /**
     * Check if a user matches the subject pattern
     *
     * @param string $pattern Subject pattern from DSL (e.g., role:admin, user:42, user:*)
     * @param SubjectInterface $user User to match against
     * @return bool True if user matches the pattern
     */
    public function matches(string $pattern, SubjectInterface $user): bool
    {
        // Parse the pattern
        if (!str_contains($pattern, ':')) {
            return false;
        }

        [$type, $value] = explode(':', $pattern, 2);

        return match ($type) {
            'role' => $this->matchesRole($value, $user),
            'user' => $this->matchesUser($value, $user),
            default => false,
        };
    }

    /**
     * Check if user has the specified role
     *
     * @param string $roleName Role name to check
     * @param SubjectInterface $user User to check
     * @return bool True if user has the role
     */
    private function matchesRole(string $roleName, SubjectInterface $user): bool
    {
        // Wildcard matches any role
        if ($roleName === '*') {
            return count($user->getRoles()) > 0;
        }

        return in_array($roleName, $user->getRoles(), true);
    }

    /**
     * Check if user ID matches the specified pattern
     *
     * @param string $userId User ID or wildcard
     * @param SubjectInterface $user User to check
     * @return bool True if user ID matches
     */
    private function matchesUser(string $userId, SubjectInterface $user): bool
    {
        // Wildcard matches any user
        if ($userId === '*') {
            return true;
        }

        // Compare user IDs (handle both string and int)
        return (string)$user->getId() === $userId;
    }
}
