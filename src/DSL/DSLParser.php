<?php

declare(strict_types=1);

namespace Authza\DSL;

use InvalidArgumentException;

class DSLParser
{
    /**
     * @return array<array<string, mixed>>
     */
    public function parseFile(string $path, ?string $format = null): array
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("File not found: {$path}");
        }

        if (!is_readable($path)) {
            throw new InvalidArgumentException("File is not readable: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new InvalidArgumentException("Failed to read file: {$path}");
        }

        if ($format === null) {
            $format = $this->detectFormat($path, $content);
        }

        return match ($format) {
            'json' => $this->parseJson($content),
            'line' => $this->parseLine($content),
            default => throw new InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    /**
     * @param array<array<string, mixed>> $rules
     */
    public function export(array $rules, string $format = 'json'): string
    {
        return match ($format) {
            'json' => json_encode($rules, JSON_PRETTY_PRINT),
            'line' => $this->exportLine($rules),
            default => throw new InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    /**
     * @param array<array<string, mixed>> $rules
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validate(array $rules): array
    {
        $errors = [];
        $warnings = [];

        foreach ($rules as $index => $rule) {
            $line = $index + 1;

            if (!isset($rule['subject'])) {
                $errors[] = "Line {$line}: Missing 'subject' field";
            }

            if (!isset($rule['resource'])) {
                $errors[] = "Line {$line}: Missing 'resource' field";
            }

            if (!isset($rule['action'])) {
                $errors[] = "Line {$line}: Missing 'action' field";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    private function detectFormat(string $path, string $content): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            return 'json';
        }

        if (in_array($ext, ['dsl', 'txt'])) {
            return 'line';
        }

        $trimmed = trim($content);
        if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
            return 'json';
        }

        return 'line';
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function parseJson(string $content): array
    {
        $data = json_decode($content, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Invalid JSON: " . json_last_error_msg());
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function parseLine(string $content): array
    {
        $rules = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));

            if (count($parts) < 3) {
                continue;
            }

            $rule = [
                'subject' => $parts[0],
                'resource' => $parts[1],
                'action' => $parts[2]
            ];

            if (isset($parts[3])) {
                $rule['condition'] = $parts[3];
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * @param array<array<string, mixed>> $rules
     */
    private function exportLine(array $rules): string
    {
        $lines = [];

        foreach ($rules as $rule) {
            $parts = [
                $rule['subject'] ?? '',
                $rule['resource'] ?? '',
                $rule['action'] ?? ''
            ];

            if (isset($rule['condition'])) {
                $parts[] = $rule['condition'];
            }

            $lines[] = implode(', ', $parts);
        }

        return implode("\n", $lines);
    }
}
