<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Adapters\Cache\ArrayCache;
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
        }

        return $this->permissionGraph;
    }

    public function getCache(): CacheInterface
    {
        if ($this->cache === null) {
            $this->cache = new ArrayCache();
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
