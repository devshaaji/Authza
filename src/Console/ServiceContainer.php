<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Adapters\Cache\ArrayCache;
use Authza\Adapters\Cache\FileCache;
use Authza\Core\Authorization;
use Authza\Core\Graph\PermissionGraph;
use Authza\Core\PolicyRegistry;
use Authza\DSL\DslImporter;
use Authza\DSL\DslValidator;
use Authza\DSL\JsonDslParser;
use Authza\DSL\LineDslParser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class ServiceContainer
{
    private ConsoleConfig $config;
    private ?Authorization $authorization = null;
    private ?PermissionGraph $permissionGraph = null;
    private ?CacheInterface $cache = null;
    private ?LoggerInterface $logger = null;
    private ?DslImporter $dslImporter = null;
    private ?DslValidator $dslValidator = null;
    private ?JsonDslParser $jsonParser = null;
    private ?LineDslParser $lineParser = null;

    public function __construct(ConsoleConfig $config)
    {
        $this->config = $config;
    }

    public function getAuthorization(): Authorization
    {
        if ($this->authorization === null) {
            $registry = new PolicyRegistry();
            
            $this->authorization = new Authorization(
                $registry,
                $this->getCache(),
                $this->getLogger(),
                $this->getPermissionGraph()
            );
        }

        return $this->authorization;
    }

    public function getPermissionGraph(): PermissionGraph
    {
        if ($this->permissionGraph === null) {
            $this->permissionGraph = new PermissionGraph($this->getCache());
            
            // Load from storage file if configured
            $storagePath = $this->config->get('graph.storage');
            if ($storagePath !== null && file_exists($storagePath)) {
                $this->loadGraphFromStorage($storagePath);
            }
        }

        return $this->permissionGraph;
    }

    /**
     * Load permission graph data from a storage file
     *
     * @param string $storagePath Path to storage file
     * @return void
     */
    private function loadGraphFromStorage(string $storagePath): void
    {
        $content = file_get_contents($storagePath);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return;
        }

        // Load rules into the graph
        if (isset($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                if (is_array($rule)) {
                    $this->permissionGraph->addRule($rule);
                }
            }
        }
    }

    /**
     * Save permission graph data to storage file
     *
     * @return bool True if saved successfully
     */
    public function saveGraphToStorage(): bool
    {
        $storagePath = $this->config->get('graph.storage');
        if ($storagePath === null) {
            return false;
        }

        // Ensure directory exists
        $dir = dirname($storagePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
        }

        $graph = $this->getPermissionGraph();
        $rules = $graph->getRules();

        $data = [
            'version' => '1.0.0',
            'created_at' => date('c'),
            'rules' => $rules,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($storagePath, $json) !== false;
    }

    public function getCache(): CacheInterface
    {
        if ($this->cache === null) {
            $adapter = $this->config->get('cache.adapter', 'array');
            $cachePath = $this->config->get('cache.path');

            if ($adapter === 'file' && $cachePath !== null) {
                $this->cache = new FileCache($cachePath);
            } else {
                $this->cache = new ArrayCache();
            }
        }

        return $this->cache;
    }

    public function getDslImporter(): DslImporter
    {
        if ($this->dslImporter === null) {
            $this->dslImporter = new DslImporter($this->getPermissionGraph());
        }

        return $this->dslImporter;
    }

    public function getDslValidator(): DslValidator
    {
        if ($this->dslValidator === null) {
            $this->dslValidator = new DslValidator();
        }

        return $this->dslValidator;
    }

    public function getJsonParser(): JsonDslParser
    {
        if ($this->jsonParser === null) {
            $this->jsonParser = new JsonDslParser();
        }

        return $this->jsonParser;
    }

    public function getLineParser(): LineDslParser
    {
        if ($this->lineParser === null) {
            $this->lineParser = new LineDslParser();
        }

        return $this->lineParser;
    }

    public function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    public function getConfig(): ConsoleConfig
    {
        return $this->config;
    }
}
