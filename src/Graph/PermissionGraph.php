<?php

declare(strict_types=1);

namespace Authza\Graph;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PermissionGraph
{
    private ?CacheItemPoolInterface $cache = null;
    private LoggerInterface $logger;

    /** @var array<string, mixed> */
    private array $rules = [];

    /** @var array<string, mixed> */
    private array $stats = [
        'total_rules' => 0,
        'by_resource_type' => [],
        'by_subject_type' => []
    ];

    private ?string $storagePath = null;

    public function __construct(
        ?CacheItemPoolInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?string $storagePath = null
    ) {
        $this->cache = $cache;
        $this->logger = $logger ?? new NullLogger();
        $this->storagePath = $storagePath;

        if ($this->storagePath && file_exists($this->storagePath)) {
            $this->load();
        }
    }

    /**
     * @param array<string, mixed> $rule
     */
    public function addRule(array $rule): void
    {
        $key = $this->getRuleKey($rule);
        $this->rules[$key] = $rule;
        $this->updateStats($rule);
        $this->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return array_values($this->rules);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    public function clear(?string $subject = null): void
    {
        if ($subject === null) {
            $this->rules = [];
            $this->stats = [
                'total_rules' => 0,
                'by_resource_type' => [],
                'by_subject_type' => []
            ];
        } else {
            foreach ($this->rules as $key => $rule) {
                if (($rule['subject'] ?? '') === $subject) {
                    unset($this->rules[$key]);
                }
            }
        }
        $this->save();
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function getRuleKey(array $rule): string
    {
        return md5(json_encode([
            $rule['subject'] ?? '',
            $rule['resource'] ?? '',
            $rule['action'] ?? ''
        ]));
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function updateStats(array $rule): void
    {
        $this->stats = [
            'total_rules' => count($this->rules),
            'by_resource_type' => [],
            'by_subject_type' => []
        ];

        foreach ($this->rules as $r) {
            if (isset($r['resource'])) {
                $resourceType = explode(':', $r['resource'])[0];
                $this->stats['by_resource_type'][$resourceType] =
                    ($this->stats['by_resource_type'][$resourceType] ?? 0) + 1;
            }

            if (isset($r['subject'])) {
                $subjectType = explode(':', $r['subject'])[0];
                $this->stats['by_subject_type'][$subjectType] =
                    ($this->stats['by_subject_type'][$subjectType] ?? 0) + 1;
            }
        }
    }

    private function save(): void
    {
        if (!$this->storagePath) {
            return;
        }

        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'rules' => $this->rules,
            'stats' => $this->stats
        ];

        file_put_contents($this->storagePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function load(): void
    {
        if (!$this->storagePath || !file_exists($this->storagePath)) {
            return;
        }

        $content = file_get_contents($this->storagePath);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return;
        }

        $this->rules = $data['rules'] ?? [];
        $this->stats = $data['stats'] ?? [
            'total_rules' => 0,
            'by_resource_type' => [],
            'by_subject_type' => []
        ];
    }
}
