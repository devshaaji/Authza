<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Core\Authorization;
use Authza\Core\Graph\PermissionGraph;
use Authza\Core\PolicyRegistry;
use Authza\DSL\DslImporter;
use Authza\DSL\DslPolicySource;
use Authza\DSL\DslValidator;
use Authza\DSL\JsonDslParser;
use Authza\DSL\LineDslParser;
use Authza\Interfaces\PolicyInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class ServiceContainer
{
    private ConsoleConfig $config;
    private ?Authorization $authorization = null;
    private ?PolicyRegistry $policyRegistry = null;
    private ?PermissionGraph $permissionGraph = null;
    private ?CacheInterface $cache = null;
    private ?LoggerInterface $logger = null;
    private ?DslImporter $dslImporter = null;
    private ?DslValidator $dslValidator = null;
    private ?JsonDslParser $jsonParser = null;
    private ?LineDslParser $lineParser = null;
    private bool $bootstrapped = false;

    public function __construct(ConsoleConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Bootstrap the container by loading all policies and DSL sources
     * 
     * This method should be called after construction to fully initialize
     * the authorization system with all configured policies.
     *
     * @return self
     * @throws \RuntimeException If required cache instance is not provided
     */
    public function bootstrap(): self
    {
        if ($this->bootstrapped) {
            return $this;
        }

        // Initialize cache and logger from config (requires explicit instance)
        $this->initializeServices();

        // Initialize permission graph (requires cache)
        $this->getPermissionGraph();

        // Load explicitly registered PHP policies (no auto-discovery for security)
        $this->loadExplicitPolicies();

        // Load DSL policy sources from explicit file list
        $this->loadDslSources();

        // Load graph from storage if available
        $this->loadGraphFromStorage();

        // Execute custom bootstrap callback if provided
        $bootstrapCallback = $this->config->get('bootstrap');
        if (is_callable($bootstrapCallback)) {
            $bootstrapCallback($this);
        }

        $this->bootstrapped = true;

        return $this;
    }

    /**
     * Initialize cache and logger services from configuration
     * 
     * Cache is optional - if not provided, the system will evaluate policies directly.
     * Logger is optional - defaults to NullLogger if not provided.
     */
    private function initializeServices(): void
    {
        // Initialize cache (optional - system works without caching)
        if ($this->cache === null) {
            $cacheInstance = $this->config->get('cache.instance');
            
            if ($cacheInstance instanceof CacheInterface) {
                $this->cache = $cacheInstance;
            }
            // If no cache provided, $this->cache remains null
        }

        // Initialize logger (optional - defaults to NullLogger)
        if ($this->logger === null) {
            $loggerInstance = $this->config->get('logger.instance');
            
            if ($loggerInstance instanceof LoggerInterface) {
                $this->logger = $loggerInstance;
            } else {
                $this->logger = new NullLogger();
            }
        }
    }

    /**
     * Load explicitly registered PHP policies
     * 
     * Security: No auto-discovery - all policies must be explicitly listed
     * in the configuration to prevent loading malicious code.
     */
    private function loadExplicitPolicies(): void
    {
        $policies = $this->config->get('policies.register', []);
        
        if (!is_array($policies)) {
            return;
        }

        foreach ($policies as $resourceType => $policy) {
            if (!is_string($resourceType)) {
                continue;
            }

            // Policy can be an instance or a class name
            if ($policy instanceof PolicyInterface) {
                $this->getPolicyRegistry()->register($resourceType, $policy);
            } elseif (is_string($policy) && class_exists($policy)) {
                $instance = new $policy();
                if ($instance instanceof PolicyInterface) {
                    $this->getPolicyRegistry()->register($resourceType, $instance);
                } else {
                    $this->getLogger()->warning("Policy class does not implement PolicyInterface: {$policy}");
                }
            } else {
                $this->getLogger()->warning("Invalid policy configuration for resource type: {$resourceType}");
            }
        }
    }

    /**
     * Load DSL policy sources from explicit file configuration
     * 
     * Security: Only loads files explicitly listed in configuration.
     * Glob patterns are supported but must be explicitly configured.
     */
    private function loadDslSources(): void
    {
        $dslFiles = $this->config->get('dsl.files', []);
        
        if (!is_array($dslFiles)) {
            $dslFiles = [$dslFiles];
        }

        foreach ($dslFiles as $pattern) {
            if (!is_string($pattern)) {
                continue;
            }

            // Handle glob patterns
            $files = glob($pattern);
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $this->loadDslFile($file);
            }
        }
    }

    /**
     * Load a single DSL file into the permission graph
     *
     * @param string $filePath Path to DSL file
     */
    private function loadDslFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $parser = match ($extension) {
            'json' => $this->getJsonParser(),
            'dsl', 'txt' => $this->getLineParser(),
            default => null,
        };

        if ($parser === null) {
            return;
        }

        try {
            $source = new DslPolicySource($parser, null, null, $filePath);
            $policies = $source->load();

            // Register the source with the policy registry
            $this->getPolicyRegistry()->registerSource($source);

            // Add rules to the permission graph
            foreach ($policies as $policy) {
                $this->getPermissionGraph()->addRule($policy->toArray());
            }

            $this->getPermissionGraph()->flush();
        } catch (\Exception $e) {
            $this->getLogger()->warning('Failed to load DSL file: ' . $filePath, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Load permission graph from storage file
     */
    private function loadGraphFromStorage(): void
    {
        $storagePath = $this->config->get('graph.storage');
        
        if ($storagePath === null || !file_exists($storagePath)) {
            return;
        }

        $content = file_get_contents($storagePath);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return;
        }

        if (isset($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                if (is_array($rule)) {
                    $this->permissionGraph->addRule($rule);
                }
            }
            $this->permissionGraph->flush();
        }
    }

    public function getPolicyRegistry(): PolicyRegistry
    {
        if ($this->policyRegistry === null) {
            $this->policyRegistry = new PolicyRegistry();
        }

        return $this->policyRegistry;
    }

    public function getAuthorization(): Authorization
    {
        if ($this->authorization === null) {
            $this->authorization = new Authorization(
                $this->getPolicyRegistry(),
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
        }

        return $this->permissionGraph;
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

    /**
     * Set a custom cache implementation
     *
     * @param CacheInterface $cache Custom cache instance
     * @return self
     */
    public function setCache(CacheInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Set a custom logger implementation
     *
     * @param LoggerInterface $logger Custom logger instance
     * @return self
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Get the cache instance (may be null if not configured)
     * 
     * @return CacheInterface|null
     */
    public function getCache(): ?CacheInterface
    {
        if ($this->cache === null) {
            $this->initializeServices();
        }

        return $this->cache;
    }

    /**
     * Get the logger instance
     * 
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->initializeServices();
        }

        return $this->logger;
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

    public function getConfig(): ConsoleConfig
    {
        return $this->config;
    }

    /**
     * Check if the container has been bootstrapped
     *
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return $this->bootstrapped;
    }
}
