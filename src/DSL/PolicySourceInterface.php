<?php

declare(strict_types=1);

namespace Authza\DSL;

/**
 * Interface for loading policies from various sources
 */
interface PolicySourceInterface
{
    /**
     * Load policies from the source
     *
     * @return array<PolicyDefinition> Array of PolicyDefinition objects
     * @throws \Authza\Exceptions\DslParseException If loading or parsing fails
     */
    public function load(): array;
}
