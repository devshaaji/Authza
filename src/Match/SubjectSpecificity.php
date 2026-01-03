<?php

declare(strict_types=1);

namespace Authza\Match;

/**
 * SubjectSpecificity calculates structural specificity scores for subjects.
 * 
 * Subjects are treated as opaque strings. Authorization priority is determined
 * only by wildcard position and depth, never by semantic prefixes.
 * 
 * Scoring rules:
 * - Each non-wildcard segment contributes 1 to the score
 * - Wildcard (*) segments contribute 0
 * - Higher scores indicate more specific subjects
 * 
 * Examples:
 * - "client:42" → score 2 (two non-wildcard segments)
 * - "permission:admin" → score 2
 * - "user:*" → score 1 (one non-wildcard, one wildcard)
 * - "*:*" → score 0 (all wildcards)
 * - "*" → score 0 (global wildcard)
 */
class SubjectSpecificity
{
    /**
     * Calculate the specificity score for a subject.
     * 
     * Higher scores indicate more specific subjects that should be
     * evaluated first during authorization checks.
     *
     * @param string $subject The subject identifier
     * @return int The specificity score (0 or higher)
     */
    public static function score(string $subject): int
    {
        if ($subject === '' || $subject === '*') {
            return 0;
        }

        $segments = explode(':', $subject);
        $score = 0;

        foreach ($segments as $segment) {
            if ($segment !== '*' && $segment !== '') {
                $score++;
            }
        }

        return $score;
    }

    /**
     * Sort subjects by specificity in descending order (most specific first).
     * 
     * This ensures that more specific rules are evaluated before wildcards,
     * which is critical for correct deny/allow resolution.
     *
     * @param array<string> $subjects Array of subject identifiers
     * @return array<string> Sorted array of subject identifiers
     */
    public static function sortBySpecificity(array $subjects): array
    {
        // Create array with scores for stable sorting
        $scored = [];
        foreach ($subjects as $index => $subject) {
            $scored[] = [
                'subject' => $subject,
                'score' => self::score($subject),
                'index' => $index, // Preserve original order for ties
            ];
        }

        // Sort by score descending, then by original index for stability
        usort($scored, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] - $a['score']; // Descending by score
            }
            return $a['index'] - $b['index']; // Ascending by original index (stable)
        });

        return array_column($scored, 'subject');
    }

    /**
     * Compare two subjects by specificity.
     * 
     * Returns negative if $a is more specific, positive if $b is more specific,
     * zero if equal specificity.
     *
     * @param string $a First subject
     * @param string $b Second subject
     * @return int Comparison result
     */
    public static function compare(string $a, string $b): int
    {
        return self::score($b) - self::score($a); // Descending order
    }
}
