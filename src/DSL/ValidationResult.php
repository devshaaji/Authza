<?php

declare(strict_types=1);

namespace Authza\DSL;

/**
 * Value object for DSL validation results
 */
class ValidationResult
{
    private array $errors = [];
    private array $warnings = [];

    /**
     * Add an error message
     *
     * @param string $message Error message
     */
    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Add a warning message
     *
     * @param string $message Warning message
     */
    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Check if validation passed (no errors)
     *
     * @return bool True if no errors exist
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get all error messages
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warning messages
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if there are any warnings
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get count of errors
     *
     * @return int
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get count of warnings
     *
     * @return int
     */
    public function getWarningCount(): int
    {
        return count($this->warnings);
    }
}
