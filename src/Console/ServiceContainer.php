<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Authorization;
use Authza\Graph\PermissionGraph;
use Authza\DSL\DSLParser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ServiceContainer
{
    private ConsoleConfig $config;
    private ?Authorization $authorization = null;
    private ?PermissionGraph $permissionGraph = null;
    private ?DSLParser $dslParser = null;
    private ?LoggerInterface $logger = null;

    public function __construct(ConsoleConfig $config)
    {
        $this->config = $config;
    }

    public function getAuthorization(): Authorization
    {
        if ($this->authorization === null) {
            $this->authorization = new Authorization(
                null,
                $this->getLogger()
            );
        }

        return $this->authorization;
    }

    public function getPermissionGraph(): PermissionGraph
    {
        if ($this->permissionGraph === null) {
            $storagePath = $this->config->get('graph.storage');
            if (!$storagePath) {
                $storagePath = sys_get_temp_dir() . '/authza_graph.json';
            }

            $this->permissionGraph = new PermissionGraph(
                null,
                $this->getLogger(),
                $storagePath
            );
        }

        return $this->permissionGraph;
    }

    public function getDSLParser(): DSLParser
    {
        if ($this->dslParser === null) {
            $this->dslParser = new DSLParser();
        }

        return $this->dslParser;
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
