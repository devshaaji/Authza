<?php

declare(strict_types=1);

namespace Authza\Tests\Match;

use Authza\Match\SubjectSpecificity;
use PHPUnit\Framework\TestCase;

class SubjectSpecificityTest extends TestCase
{
    /**
     * @dataProvider scoreProvider
     */
    public function testScore(string $subject, int $expectedScore): void
    {
        $this->assertSame($expectedScore, SubjectSpecificity::score($subject));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function scoreProvider(): array
    {
        return [
            // Fully specific subjects (score = segment count)
            'client:42' => ['client:42', 2],
            'permission:admin' => ['permission:admin', 2],
            'user:123' => ['user:123', 2],
            'role:manager' => ['role:manager', 2],
            'tenant:acme:user:42' => ['tenant:acme:user:42', 4],
            
            // Partial wildcards (wildcards don't count)
            'user:*' => ['user:*', 1],
            'role:*' => ['role:*', 1],
            'client:*' => ['client:*', 1],
            '*:admin' => ['*:admin', 1],
            '*:42' => ['*:42', 1],
            'tenant:*:user:42' => ['tenant:*:user:42', 3],
            'tenant:acme:*:*' => ['tenant:acme:*:*', 2],
            
            // Full wildcards (score = 0)
            '*' => ['*', 0],
            '*:*' => ['*:*', 0],
            '*:*:*' => ['*:*:*', 0],
            
            // Edge cases
            'empty' => ['', 0],
            'single segment' => ['admin', 1],
            'trailing colon' => ['user:', 1],
            'leading colon' => [':admin', 1],
        ];
    }

    public function testSortBySpecificity(): void
    {
        $subjects = [
            '*',
            'user:*',
            'client:42',
            '*:*',
            'permission:admin',
            'client:*',
        ];

        $sorted = SubjectSpecificity::sortBySpecificity($subjects);

        // Most specific first (score 2), then score 1, then score 0
        $this->assertSame('client:42', $sorted[0]);
        $this->assertSame('permission:admin', $sorted[1]);
        $this->assertSame('user:*', $sorted[2]);
        $this->assertSame('client:*', $sorted[3]);
        // Score 0 items preserve original order
        $this->assertSame('*', $sorted[4]);
        $this->assertSame('*:*', $sorted[5]);
    }

    public function testSortBySpecificityPreservesOrderForEqualScores(): void
    {
        $subjects = [
            'role:admin',
            'user:42',
            'client:acme',
            'permission:read',
        ];

        $sorted = SubjectSpecificity::sortBySpecificity($subjects);

        // All have score 2, original order should be preserved
        $this->assertSame(['role:admin', 'user:42', 'client:acme', 'permission:read'], $sorted);
    }

    public function testSortBySpecificityEmptyArray(): void
    {
        $this->assertSame([], SubjectSpecificity::sortBySpecificity([]));
    }

    public function testSortBySpecificitySingleElement(): void
    {
        $this->assertSame(['user:42'], SubjectSpecificity::sortBySpecificity(['user:42']));
    }

    public function testCompare(): void
    {
        // More specific should come first (negative return)
        $this->assertLessThan(0, SubjectSpecificity::compare('user:42', 'user:*'));
        $this->assertLessThan(0, SubjectSpecificity::compare('user:42', '*'));
        $this->assertLessThan(0, SubjectSpecificity::compare('user:*', '*'));
        
        // Equal specificity
        $this->assertSame(0, SubjectSpecificity::compare('user:42', 'role:admin'));
        $this->assertSame(0, SubjectSpecificity::compare('*', '*:*'));
        
        // Less specific should come after (positive return)
        $this->assertGreaterThan(0, SubjectSpecificity::compare('user:*', 'user:42'));
        $this->assertGreaterThan(0, SubjectSpecificity::compare('*', 'user:42'));
    }

    public function testArbitrarySubjectNamesAreSupported(): void
    {
        // These should all work without any special treatment
        $arbitrarySubjects = [
            'actor:system',
            'service:payment-gateway',
            'api_key:sk_live_123',
            'device:mobile:ios:12345',
            'org:acme:team:engineering:member:jane',
        ];

        foreach ($arbitrarySubjects as $subject) {
            $score = SubjectSpecificity::score($subject);
            $this->assertGreaterThan(0, $score, "Subject '$subject' should have positive score");
        }

        // Multi-segment subjects should have higher scores
        $this->assertSame(2, SubjectSpecificity::score('actor:system'));
        $this->assertSame(4, SubjectSpecificity::score('device:mobile:ios:12345'));
        $this->assertSame(6, SubjectSpecificity::score('org:acme:team:engineering:member:jane'));
    }

    public function testNoSemanticInterpretation(): void
    {
        // These should all have the same score - no prefix is "special"
        $subjects = [
            'user:42',
            'role:admin',
            'client:acme',
            'tenant:123',
            'permission:read',
            'actor:system',
            'whatever:something',
        ];

        $scores = array_map(fn($s) => SubjectSpecificity::score($s), $subjects);
        
        // All should be score 2 (two non-wildcard segments)
        $this->assertSame(array_fill(0, count($subjects), 2), $scores);
    }
}
