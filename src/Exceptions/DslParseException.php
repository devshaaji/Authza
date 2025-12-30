<?php

declare(strict_types=1);

namespace Authza\Exceptions;

use Exception;

/**
 * Exception thrown when DSL parsing fails
 */
class DslParseException extends Exception
{
    private ?int $lineNumber = null;
    private ?string $lineContent = null;

    /**
     * Create a new DSL parse exception
     *
     * @param string $message The error message
     * @param int|null $lineNumber Optional line number where error occurred
     * @param string|null $lineContent Optional content of the line that caused the error
     */
    public function __construct(string $message, ?int $lineNumber = null, ?string $lineContent = null)
    {
        $fullMessage = $message;
        
        if ($lineNumber !== null) {
            $fullMessage .= " at line {$lineNumber}";
        }
        
        if ($lineContent !== null) {
            $fullMessage .= ": {$lineContent}";
        }
        
        parent::__construct($fullMessage);
        
        $this->lineNumber = $lineNumber;
        $this->lineContent = $lineContent;
    }

    /**
     * Get the line number where the error occurred
     *
     * @return int|null
     */
    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    /**
     * Get the content of the line that caused the error
     *
     * @return string|null
     */
    public function getLineContent(): ?string
    {
        return $this->lineContent;
    }
}
