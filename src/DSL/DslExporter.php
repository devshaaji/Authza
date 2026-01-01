<?php

declare(strict_types=1);

namespace Authza\DSL;

use Authza\Exceptions\DslParseException;

/**
 * Export policies to DSL formats
 * 
 * Supports exporting policies to JSON and line-based DSL formats
 */
class DslExporter
{
    /**
     * @var array<PolicyDefinition>
     */
    private array $policies;

    /**
     * Create a new DSL exporter
     *
     * @param array<PolicyDefinition> $policies Array of PolicyDefinition objects
     */
    public function __construct(array $policies = [])
    {
        $this->policies = $policies;
    }

    /**
     * Set the policies to export
     *
     * @param array<PolicyDefinition> $policies Array of PolicyDefinition objects
     * @return void
     */
    public function setPolicies(array $policies): void
    {
        $this->policies = $policies;
    }

    /**
     * Export policies to the specified format
     *
     * @param string $format Export format: 'json' or 'line' (default: 'json')
     * @return string Exported content
     * @throws \InvalidArgumentException If format is not supported
     */
    public function export(string $format = 'json'): string
    {
        return match ($format) {
            'json' => $this->exportToJson(),
            'line' => $this->exportToLine(),
            default => throw new \InvalidArgumentException(
                "Unsupported export format: {$format}. Supported formats: json, line"
            ),
        };
    }

    /**
     * Export policies to a file
     *
     * @param string $filePath Path to output file
     * @param string $format Export format: 'json' or 'line'
     * @return bool True on success, false on failure
     * @throws \InvalidArgumentException If format is not supported
     */
    public function exportToFile(string $filePath, string $format = 'json'): bool
    {
        $content = $this->export($format);

        $result = file_put_contents($filePath, $content);

        return $result !== false;
    }

    /**
     * Export policies to JSON format
     *
     * @return string JSON string
     */
    private function exportToJson(): string
    {
        $data = array_map(
            fn(PolicyDefinition $policy) => $policy->toArray(),
            $this->policies
        );

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export policies to line-based DSL format
     *
     * @return string Line-based DSL content
     */
    private function exportToLine(): string
    {
        $lines = [];

        foreach ($this->policies as $policy) {
            $parts = [
                $policy->subject,
                $policy->resource,
                $policy->action,
            ];

            if ($policy->condition !== null) {
                $parts[] = $policy->condition;
            }

            // Add effect if it's deny (allow is default so we don't need to write it)
            if ($policy->effect === 'deny') {
                // If no condition, add empty string placeholder
                if ($policy->condition === null) {
                    $parts[] = '';
                }
                $parts[] = 'deny';
            }

            $lines[] = implode(', ', $parts);
        }

        return implode("\n", $lines);
    }
}
