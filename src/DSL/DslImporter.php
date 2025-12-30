<?php

declare(strict_types=1);

namespace Authza\DSL;

use Authza\Core\Graph\PermissionGraph;
use Authza\Exceptions\DslParseException;

/**
 * Import DSL rules into PermissionGraph
 */
class DslImporter
{
    private PermissionGraph $graph;

    public function __construct(PermissionGraph $graph)
    {
        $this->graph = $graph;
    }

    /**
     * Import rules from DSL content
     *
     * @param DslParserInterface $parser Parser to use
     * @param string $content DSL content to import
     * @return int Number of rules imported
     * @throws DslParseException If parsing fails
     */
    public function import(DslParserInterface $parser, string $content): int
    {
        $rules = $parser->parse($content);
        
        if (empty($rules)) {
            return 0;
        }
        
        $this->graph->precomputeFromDsl($rules);
        
        return count($rules);
    }

    /**
     * Import rules from a file, auto-detecting format
     *
     * @param string $filePath Path to DSL file
     * @return int Number of rules imported
     * @throws DslParseException If file cannot be read or parsed
     */
    public function importFromFile(string $filePath): int
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
        
        $parser = $this->detectParser($filePath);
        
        return $this->import($parser, $content);
    }

    /**
     * Detect appropriate parser based on file extension
     *
     * @param string $filePath File path
     * @return DslParserInterface
     * @throws DslParseException If format cannot be detected
     */
    private function detectParser(string $filePath): DslParserInterface
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        return match ($extension) {
            'json' => new JsonDslParser(),
            'dsl', 'txt' => new LineDslParser(),
            default => throw new DslParseException(
                "Unsupported file format: .{$extension}. Supported formats: .json, .dsl, .txt"
            ),
        };
    }
}
