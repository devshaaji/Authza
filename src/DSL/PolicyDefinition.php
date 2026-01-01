<?php

declare(strict_types=1);

namespace Authza\DSL;

/**
 * PolicyDefinition DTO - Canonical format for DSL policies
 * 
 * Serves as the bridge between DSL parsing and the authorization engine
 */
final class PolicyDefinition
{
    /**
     * Create a new policy definition
     *
     * @param string $subject Subject pattern (e.g., role:admin, user:42, user:*)
     * @param string $resource Resource pattern (e.g., invoice, invoice:123, invoice:*)
     * @param string $action Action to be performed (e.g., create, edit, delete)
     * @param string|null $condition Optional condition (e.g., owner, department==finance)
     * @param string $effect Permission effect - either 'allow' or 'deny'
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $resource,
        public readonly string $action,
        public readonly ?string $condition = null,
        public readonly string $effect = 'allow'
    ) {
        if (!in_array($effect, ['allow', 'deny'], true)) {
            throw new \InvalidArgumentException("Effect must be 'allow' or 'deny', got: {$effect}");
        }
    }

    /**
     * Create PolicyDefinition from array
     *
     * @param array<string, mixed> $data Array with keys: subject, resource, action, condition, effect
     * @return self
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['subject'])) {
            throw new \InvalidArgumentException("Missing required field 'subject'");
        }

        if (!isset($data['resource'])) {
            throw new \InvalidArgumentException("Missing required field 'resource'");
        }

        if (!isset($data['action'])) {
            throw new \InvalidArgumentException("Missing required field 'action'");
        }

        return new self(
            subject: (string)$data['subject'],
            resource: (string)$data['resource'],
            action: (string)$data['action'],
            condition: isset($data['condition']) ? (string)$data['condition'] : null,
            effect: isset($data['effect']) ? (string)$data['effect'] : 'allow'
        );
    }

    /**
     * Convert PolicyDefinition to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'subject' => $this->subject,
            'resource' => $this->resource,
            'action' => $this->action,
            'effect' => $this->effect,
        ];

        if ($this->condition !== null) {
            $data['condition'] = $this->condition;
        }

        return $data;
    }
}
