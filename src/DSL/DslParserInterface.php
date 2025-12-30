<?php

declare(strict_types=1);

namespace Authza\DSL;

/**
 * Interface for DSL parsers
 */
interface DslParserInterface
{
    /**
     * Parse DSL content and return array of permission rules
     *
     * @param string $content DSL content to parse
     * @return array Array of permission rules in format:
     *               [['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'create', 'condition' => null], ...]
     * @throws \Authza\Exceptions\DslParseException If parsing fails
     */
    public function parse(string $content): array;
}
