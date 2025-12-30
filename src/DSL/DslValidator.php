<?php

declare(strict_types=1);

namespace Authza\DSL;

use Authza\Exceptions\DslParseException;

/**
 * Validate DSL rules for errors and potential issues
 */
class DslValidator
{
    private const VALID_ACTIONS = ['create', 'read', 'view', 'edit', 'update', 'delete', 'approve', 'reject'];
    private const VALID_RESOURCE_TYPES = ['invoice', 'client', 'user', 'order', 'product', 'report'];

    /**
     * Validate DSL content
     *
     * @param DslParserInterface $parser Parser to use
     * @param string $content DSL content to validate
     * @return ValidationResult Validation result with errors and warnings
     */
    public function validate(DslParserInterface $parser, string $content): ValidationResult
    {
        $result = new ValidationResult();
        
        // Try to parse the content
        try {
            $rules = $parser->parse($content);
        } catch (DslParseException $e) {
            $result->addError("Parse error: " . $e->getMessage());
            return $result;
        }
        
        if (empty($rules)) {
            $result->addWarning("No rules found in DSL content");
            return $result;
        }
        
        // Validate individual rules
        $seenRules = [];
        
        foreach ($rules as $index => $rule) {
            $this->validateRule($rule, $index, $result, $seenRules);
        }
        
        return $result;
    }

    /**
     * Validate a single rule
     *
     * @param array $rule Rule to validate
     * @param int $index Rule index
     * @param ValidationResult $result Result object to add errors/warnings
     * @param array $seenRules Array to track duplicate rules
     */
    private function validateRule(array $rule, int $index, ValidationResult $result, array &$seenRules): void
    {
        // Validate subject format
        if (!preg_match('/^(role|user):.+$/', $rule['subject'])) {
            $result->addError(
                "Invalid subject at rule {$index}: Must be 'role:NAME' or 'user:ID' (got: {$rule['subject']})"
            );
        }
        
        // Check for invalid subject patterns
        if (preg_match('/^(role|user):$/', $rule['subject'])) {
            $result->addError(
                "Invalid subject at rule {$index}: Subject identifier cannot be empty after colon"
            );
        }
        
        // Validate resource format
        $resourceParts = explode(':', $rule['resource']);
        $resourceType = $resourceParts[0];
        
        // Warn about non-standard resource types (informational only)
        if (!in_array($resourceType, self::VALID_RESOURCE_TYPES, true) && $resourceType !== '*') {
            $result->addWarning(
                "Non-standard resource type at rule {$index}: '{$resourceType}'. " .
                "Common types: " . implode(', ', self::VALID_RESOURCE_TYPES)
            );
        }
        
        // Validate action
        if (!in_array($rule['action'], self::VALID_ACTIONS, true) && $rule['action'] !== '*') {
            $result->addWarning(
                "Non-standard action at rule {$index}: '{$rule['action']}'. " .
                "Common actions: " . implode(', ', self::VALID_ACTIONS)
            );
        }
        
        // Validate condition format if present
        if ($rule['condition'] !== null) {
            $this->validateCondition($rule['condition'], $index, $result);
        }
        
        // Check for duplicate rules
        $ruleKey = $this->makeRuleKey($rule);
        if (isset($seenRules[$ruleKey])) {
            $result->addWarning(
                "Duplicate rule at index {$index}: Same rule already exists at index {$seenRules[$ruleKey]}"
            );
        } else {
            $seenRules[$ruleKey] = $index;
        }
        
        // Check for conflicting rules (e.g., wildcard vs specific)
        $this->checkConflicts($rule, $index, $seenRules, $result);
    }

    /**
     * Validate a condition
     *
     * @param string $condition Condition to validate
     * @param int $index Rule index
     * @param ValidationResult $result Result object
     */
    private function validateCondition(string $condition, int $index, ValidationResult $result): void
    {
        // Check for known condition patterns
        if ($condition === 'owner') {
            return; // Valid
        }
        
        // Check for comparison conditions
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(==|!=).+$/', $condition)) {
            return; // Valid
        }
        
        $result->addWarning(
            "Unknown condition format at rule {$index}: '{$condition}'. " .
            "Known formats: 'owner', 'key==value', 'key!=value'"
        );
    }

    /**
     * Check for conflicting rules
     *
     * @param array $rule Current rule
     * @param int $index Current rule index
     * @param array $seenRules Previously seen rules
     * @param ValidationResult $result Result object
     */
    private function checkConflicts(array $rule, int $index, array $seenRules, ValidationResult $result): void
    {
        // Check if there's a wildcard rule that might conflict
        $subjectParts = explode(':', $rule['subject'], 2);
        $subjectType = $subjectParts[0];
        $wildcardSubject = "{$subjectType}:*";
        
        // Check for wildcard vs specific conflicts
        if ($rule['subject'] !== $wildcardSubject) {
            $wildcardRule = [
                'subject' => $wildcardSubject,
                'resource' => $rule['resource'],
                'action' => $rule['action'],
                'condition' => $rule['condition'],
            ];
            
            $wildcardKey = $this->makeRuleKey($wildcardRule);
            
            if (isset($seenRules[$wildcardKey])) {
                $result->addWarning(
                    "Potentially redundant rule at index {$index}: " .
                    "Wildcard rule '{$wildcardSubject}' at index {$seenRules[$wildcardKey]} may already cover this"
                );
            }
        }
    }

    /**
     * Make a unique key for a rule
     *
     * @param array $rule Rule
     * @return string Unique key
     */
    private function makeRuleKey(array $rule): string
    {
        return sprintf(
            '%s:%s:%s:%s',
            $rule['subject'],
            $rule['resource'],
            $rule['action'],
            $rule['condition'] ?? 'null'
        );
    }
}
