<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type LabelDefinition array{allowed_values?: list<string>, policy?: string}
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class LabelConfig
{
    /**
     * @param list<string>|null $allowedValues
     */
    public function __construct(
        public ?array $allowedValues = null,
        public ?LabelPolicy $policy = null,
    ) {
    }

    /**
     * @param LabelDefinition $definition
     */
    public static function fromDefinition(array $definition): self
    {
        return new self(
            allowedValues: $definition['allowed_values'] ?? null,
            policy: isset($definition['policy']) ? LabelPolicy::tryFrom($definition['policy']) : null,
        );
    }
}
