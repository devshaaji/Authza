<?php

declare(strict_types=1);

namespace Authza\DSL;

use Authza\Exceptions\DslParseException;

/**
 * Parser for line-based DSL format
 * Format: subject, resource, action, optional condition
 * Example: role:admin, invoice, create
 */
class LineDslParser implements DslParserInterface
{
    /**
     * Parse line-based DSL content
     *
     * @param string $content DSL content with one rule per line
     * @return array Array of parsed permission rules
     * @throws DslParseException If parsing fails
     */
    public function parse(string $content): array
    {
        $rules = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            $actualLineNumber = $lineNumber + 1;
            $line = trim($line);
            
            // Skip empty lines
            if ($line === '') {
                continue;
            }
            
            // Skip comments (lines starting with #)
            if (str_starts_with($line, '#')) {
                continue;
            }
            
            try {
                $rule = $this->parseLine($line);
                $rules[] = $rule;
            } catch (DslParseException $e) {
                throw new DslParseException(
                    $e->getMessage(),
                    $actualLineNumber,
                    $line
                );
            }
        }
        
        return $rules;
    }

    /**
     * Parse a single line into a permission rule
     *
     * @param string $line Line to parse
     * @return array Parsed rule
     * @throws DslParseException If line format is invalid
     */
    private function parseLine(string $line): array
    {
        $parts = array_map('trim', explode(',', $line));
        
        // Must have at least subject, resource, and action
        if (count($parts) < 3) {
            throw new DslParseException(
                "Invalid DSL syntax: Expected at least 3 comma-separated values (subject, resource, action)"
            );
        }
        
        $subject = $parts[0];
        $resource = $parts[1];
        $action = $parts[2];
        $condition = isset($parts[3]) && $parts[3] !== '' ? $parts[3] : null;
        $effect = isset($parts[4]) && $parts[4] !== '' ? $parts[4] : 'allow';
        
        // Validate required fields are not empty
        if (empty($subject)) {
            throw new DslParseException("Subject cannot be empty");
        }
        
        if (empty($resource)) {
            throw new DslParseException("Resource cannot be empty");
        }
        
        if (empty($action)) {
            throw new DslParseException("Action cannot be empty");
        }
        
        // Validate subject format (must be role:* or user:*)
        if (!preg_match('/^(role|user):.+$/', $subject)) {
            throw new DslParseException(
                "Invalid subject format: Must be 'role:NAME' or 'user:ID' (got: {$subject})"
            );
        }

        // Validate effect
        if (!in_array($effect, ['allow', 'deny'], true)) {
            throw new DslParseException(
                "Invalid effect: Must be 'allow' or 'deny' (got: {$effect})"
            );
        }
        
        return [
            'subject' => $subject,
            'resource' => $resource,
            'action' => $action,
            'condition' => $condition,
            'effect' => $effect,
        ];
    }
}
