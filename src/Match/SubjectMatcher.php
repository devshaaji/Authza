<?php

declare(strict_types=1);

namespace Authza\Match;

use Authza\Interfaces\SubjectInterface;

/**
 * Matches DSL subject patterns against users
 * 
 * Subjects are treated as opaque identifiers. Pattern matching is based
 * purely on structural specificity, not semantic interpretation.
 * 
 * Handles patterns like:
 * - role:admin (matches users with admin role)
 * - user:42 (matches user with ID 42)
 * - client:acme (matches any subject type)
 * - user:* (matches any user)
 * - * (global wildcard - matches anything)
 * 
 * No prefixes are reserved or treated specially. The system does not
 * interpret what a subject "means" - only whether it matches structurally.
 */
class SubjectMatcher
{
    /**
     * Check if a user matches the subject pattern
     *
     * @param string $pattern Subject pattern from DSL (e.g., role:admin, user:42, user:*, *)
     * @param SubjectInterface $user User to match against
     * @return bool True if user matches the pattern
     */
    public function matches(string $pattern, SubjectInterface $user): bool
    {
        // Global wildcard matches everything
        if ($pattern === '*') {
            return true;
        }

        // Empty pattern matches nothing
        if ($pattern === '') {
            return false;
        }

        // Pattern without colon - treat as type-only pattern, no match without structure
        if (!str_contains($pattern, ':')) {
            return false;
        }

        [$type, $value] = explode(':', $pattern, 2);

        // Build the set of subject identifiers this user represents
        $userSubjects = $this->buildUserSubjects($user);

        // Check for exact match first
        if (in_array($pattern, $userSubjects, true)) {
            return true;
        }

        // Check wildcard patterns (e.g., "role:*" matches any role)
        if ($value === '*') {
            return $this->matchesTypeWildcard($type, $userSubjects);
        }

        // Check if pattern matches with wildcard type (e.g., "*:admin")
        if ($type === '*') {
            return $this->matchesValueWildcard($value, $userSubjects);
        }

        return false;
    }

    /**
     * Build the set of subject identifiers for a user.
     * 
     * This creates all the subject strings that represent this user:
     * - user:<id> for the user's ID
     * - role:<name> for each role the user has
     *
     * @param SubjectInterface $user The user
     * @return array<string> Array of subject identifiers
     */
    public function buildUserSubjects(SubjectInterface $user): array
    {
        $subjects = [];

        // Add user identifier
        $subjects[] = 'user:' . (string)$user->getId();

        // Add role identifiers
        foreach ($user->getRoles() as $role) {
            $subjects[] = 'role:' . $role;
        }

        return $subjects;
    }

    /**
     * Check if any user subject matches a type wildcard pattern (e.g., "role:*")
     *
     * @param string $type The type prefix to match
     * @param array<string> $userSubjects User's subject identifiers
     * @return bool True if any subject matches
     */
    private function matchesTypeWildcard(string $type, array $userSubjects): bool
    {
        $prefix = $type . ':';
        foreach ($userSubjects as $subject) {
            if (str_starts_with($subject, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if any user subject matches a value wildcard pattern (e.g., "*:admin")
     *
     * @param string $value The value to match
     * @param array<string> $userSubjects User's subject identifiers
     * @return bool True if any subject matches
     */
    private function matchesValueWildcard(string $value, array $userSubjects): bool
    {
        $suffix = ':' . $value;
        foreach ($userSubjects as $subject) {
            if (str_ends_with($subject, $suffix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Match a pattern against a specific subject string (not a SubjectInterface).
     * 
     * This is useful for matching arbitrary subject identifiers without
     * requiring a SubjectInterface implementation.
     *
     * @param string $pattern The pattern to match (e.g., "user:*", "role:admin")
     * @param string $subject The subject identifier to match against (e.g., "user:42")
     * @return bool True if the subject matches the pattern
     */
    public function matchesSubjectString(string $pattern, string $subject): bool
    {
        // Global wildcard matches everything
        if ($pattern === '*') {
            return true;
        }

        // Exact match
        if ($pattern === $subject) {
            return true;
        }

        // Empty pattern matches nothing
        if ($pattern === '' || $subject === '') {
            return false;
        }

        // Parse pattern segments
        $patternParts = explode(':', $pattern);
        $subjectParts = explode(':', $subject);

        // Must have same number of segments for structural match
        if (count($patternParts) !== count($subjectParts)) {
            // Special case: single wildcard pattern matches multi-segment subject
            if ($pattern === '*') {
                return true;
            }
            return false;
        }

        // Compare segment by segment
        foreach ($patternParts as $i => $patternSegment) {
            $subjectSegment = $subjectParts[$i];

            // Wildcard segment matches anything
            if ($patternSegment === '*') {
                continue;
            }

            // Exact segment match required
            if ($patternSegment !== $subjectSegment) {
                return false;
            }
        }

        return true;
    }
}
