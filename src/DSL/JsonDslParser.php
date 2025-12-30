<?php

declare(strict_types=1);

namespace Authza\DSL;

use Authza\Exceptions\DslParseException;

/**
 * Parser for JSON DSL format
 * Format: Array of objects with subject, resource, action, and optional condition
 */
class JsonDslParser implements DslParserInterface
{
    /**
     * Parse JSON DSL content
     *
     * @param string $content JSON content to parse
     * @return array Array of parsed permission rules
     * @throws DslParseException If JSON is invalid or structure is incorrect
     */
    public function parse(string $content): array
    {
        $content = trim($content);
        
        if (empty($content)) {
            return [];
        }
        
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DslParseException(
                "Invalid JSON: " . json_last_error_msg()
            );
        }
        
        if (!is_array($data) || !array_is_list($data)) {
            throw new DslParseException(
                "Invalid JSON structure: Expected array of permission rules"
            );
        }
        
        $rules = [];
        
        foreach ($data as $index => $item) {
            if (!is_array($item)) {
                throw new DslParseException(
                    "Invalid rule at index {$index}: Expected object, got " . gettype($item)
                );
            }
            
            $rule = $this->validateAndNormalizeRule($item, $index);
            $rules[] = $rule;
        }
        
        return $rules;
    }

    /**
     * Validate and normalize a single rule
     *
     * @param array $item Rule data
     * @param int $index Rule index for error messages
     * @return array Normalized rule
     * @throws DslParseException If rule is invalid
     */
    private function validateAndNormalizeRule(array $item, int $index): array
    {
        // Check required fields
        if (!isset($item['subject'])) {
            throw new DslParseException(
                "Missing required field 'subject' at rule index {$index}"
            );
        }
        
        if (!isset($item['resource'])) {
            throw new DslParseException(
                "Missing required field 'resource' at rule index {$index}"
            );
        }
        
        if (!isset($item['action'])) {
            throw new DslParseException(
                "Missing required field 'action' at rule index {$index}"
            );
        }
        
        // Validate types
        if (!is_string($item['subject']) || empty($item['subject'])) {
            throw new DslParseException(
                "Invalid 'subject' at rule index {$index}: Must be a non-empty string"
            );
        }
        
        if (!is_string($item['resource']) || empty($item['resource'])) {
            throw new DslParseException(
                "Invalid 'resource' at rule index {$index}: Must be a non-empty string"
            );
        }
        
        if (!is_string($item['action']) || empty($item['action'])) {
            throw new DslParseException(
                "Invalid 'action' at rule index {$index}: Must be a non-empty string"
            );
        }
        
        // Validate subject format
        if (!preg_match('/^(role|user):.+$/', $item['subject'])) {
            throw new DslParseException(
                "Invalid subject format at rule index {$index}: Must be 'role:NAME' or 'user:ID' (got: {$item['subject']})"
            );
        }
        
        // Handle optional condition
        $condition = $item['condition'] ?? null;
        
        if ($condition !== null && (!is_string($condition) || empty($condition))) {
            throw new DslParseException(
                "Invalid 'condition' at rule index {$index}: Must be a non-empty string or null"
            );
        }
        
        return [
            'subject' => $item['subject'],
            'resource' => $item['resource'],
            'action' => $item['action'],
            'condition' => $condition,
        ];
    }
}
