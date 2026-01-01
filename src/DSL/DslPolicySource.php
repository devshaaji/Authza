<?php

declare(strict_types=1);

namespace Authza\DSL;

use Authza\Exceptions\DslParseException;

/**
 * Concrete implementation for loading policies from DSL sources
 * 
 * Wraps parser and optional validator to load PolicyDefinition objects
 */
class DslPolicySource implements PolicySourceInterface
{
    private DslParserInterface $parser;
    private ?DslValidator $validator;
    private ?string $content = null;
    private ?string $filePath = null;

    /**
     * Create a new DSL policy source
     *
     * @param DslParserInterface $parser Parser to use for DSL content
     * @param DslValidator|null $validator Optional validator for parsed rules
     * @param string|null $content Optional DSL content to load
     * @param string|null $filePath Optional file path to load from
     */
    public function __construct(
        DslParserInterface $parser,
        ?DslValidator $validator = null,
        ?string $content = null,
        ?string $filePath = null
    ) {
        $this->parser = $parser;
        $this->validator = $validator;
        $this->content = $content;
        $this->filePath = $filePath;
    }

    /**
     * Load policies from configured source (content or file)
     *
     * @return array<PolicyDefinition> Array of PolicyDefinition objects
     * @throws DslParseException If parsing or validation fails
     * @throws \BadMethodCallException If no content or file path configured
     */
    public function load(): array
    {
        if ($this->filePath !== null) {
            return $this->loadFromFile($this->filePath);
        }

        if ($this->content !== null) {
            return $this->loadFromString($this->content);
        }

        throw new \BadMethodCallException(
            "No content or file path configured. Use constructor parameters or call loadFromString()/loadFromFile() directly."
        );
    }

    /**
     * Load policies from string content
     *
     * @param string $content DSL content
     * @return array<PolicyDefinition> Array of PolicyDefinition objects
     * @throws DslParseException If parsing or validation fails
     */
    public function loadFromString(string $content): array
    {
        // Validate if validator is provided (validation also parses)
        if ($this->validator !== null) {
            $result = $this->validator->validate($this->parser, $content);
            if (!$result->isValid()) {
                throw new DslParseException(
                    "Validation failed: " . implode(", ", $result->getErrors())
                );
            }
        }

        // Parse the content
        $rules = $this->parser->parse($content);

        // Convert to PolicyDefinition objects
        return array_map(
            fn(array $rule) => PolicyDefinition::fromArray($rule),
            $rules
        );
    }

    /**
     * Load policies from a file
     *
     * @param string $filePath Path to DSL file
     * @return array<PolicyDefinition> Array of PolicyDefinition objects
     * @throws DslParseException If file cannot be read or parsing fails
     */
    public function loadFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new DslParseException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new DslParseException("File not readable: {$filePath}");
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new DslParseException("Failed to read file: {$filePath}");
        }

        return $this->loadFromString($content);
    }
}
