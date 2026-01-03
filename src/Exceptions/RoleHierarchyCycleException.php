<?php

declare(strict_types=1);

namespace Authza\Exceptions;

/**
 * Exception thrown when a cycle is detected in role hierarchies
 * 
 * Example: role:admin → role:manager → role:admin
 * This would create infinite expansion when collapsing the hierarchy
 */
class RoleHierarchyCycleException extends \LogicException
{
    /**
     * @var array<string> The chain of roles that forms the cycle
     */
    private array $cycle = [];

    /**
     * Create a new RoleHierarchyCycleException
     *
     * @param array<string> $cycle The roles involved in the cycle
     * @param string $message Optional custom message
     */
    public function __construct(array $cycle = [], string $message = '')
    {
        $this->cycle = $cycle;
        
        if (empty($message)) {
            $message = $this->buildMessage();
        }
        
        parent::__construct($message);
    }

    /**
     * Get the cycle of roles
     *
     * @return array<string>
     */
    public function getCycle(): array
    {
        return $this->cycle;
    }

    /**
     * Build a descriptive error message from the cycle
     *
     * @return string
     */
    private function buildMessage(): string
    {
        if (empty($this->cycle)) {
            return 'Cycle detected in role hierarchy';
        }

        $cycleStr = implode(' → ', $this->cycle);
        return "Cycle detected in role hierarchy: $cycleStr";
    }
}
