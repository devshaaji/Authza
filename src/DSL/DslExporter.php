<?php

declare(strict_types=1);

namespace Authza\DSL;

use Authza\Core\Graph\PermissionGraph;
use InvalidArgumentException;

/**
 * Export PermissionGraph to DSL formats
 */
class DslExporter
{
    private PermissionGraph $graph;

    public function __construct(PermissionGraph $graph)
    {
        $this->graph = $graph;
    }

    /**
     * Export permission graph to DSL format
     *
     * @param string $format Export format ('json' or 'line')
     * @return string Exported DSL content
     * @throws InvalidArgumentException If format is not supported
     */
    public function export(string $format = 'json'): string
    {
        $permissions = $this->graph->getAllPermissions();
        
        return match ($format) {
            'json' => $this->exportToJson($permissions),
            'line' => $this->exportToLine($permissions),
            default => throw new InvalidArgumentException(
                "Unsupported format: {$format}. Supported formats: json, line"
            ),
        };
    }

    /**
     * Export to file
     *
     * @param string $filePath File path to export to
     * @param string $format Export format ('json' or 'line')
     * @return bool True if successful
     * @throws InvalidArgumentException If format is not supported
     */
    public function exportToFile(string $filePath, string $format): bool
    {
        $content = $this->export($format);
        
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        return file_put_contents($filePath, $content) !== false;
    }

    /**
     * Export permissions to JSON format
     *
     * @param array $permissions Permissions to export
     * @return string JSON string
     */
    private function exportToJson(array $permissions): string
    {
        $data = array_map(function ($permission) {
            $rule = [
                'subject' => $permission['subject'],
                'resource' => $permission['resource'],
                'action' => $permission['action'],
            ];
            
            if ($permission['condition'] !== null) {
                $rule['condition'] = $permission['condition'];
            }
            
            return $rule;
        }, $permissions);
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * Export permissions to line-based format
     *
     * @param array $permissions Permissions to export
     * @return string Line-based DSL string
     */
    private function exportToLine(array $permissions): string
    {
        $lines = [];
        
        foreach ($permissions as $permission) {
            $parts = [
                $permission['subject'],
                $permission['resource'],
                $permission['action'],
            ];
            
            if ($permission['condition'] !== null) {
                $parts[] = $permission['condition'];
            }
            
            $lines[] = implode(', ', $parts);
        }
        
        return implode("\n", $lines) . (empty($lines) ? '' : "\n");
    }
}
