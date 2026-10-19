<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type LabelDefinition array{allowed_values?: list<string>, add_allowed_values?: list<string>, policy?: string}
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
        // the config tree folds the two keys at container build: a non-empty `allowed_values`
        // is the complete final list (locked label), otherwise `add_allowed_values` is
        $allowedValues = $definition['allowed_values'] ?? [];
        if ($allowedValues === []) {
            $allowedValues = $definition['add_allowed_values'] ?? [];
        }

        return new self(
            allowedValues: $allowedValues === [] ? null : $allowedValues,
            policy: isset($definition['policy']) ? LabelPolicy::tryFrom($definition['policy']) : null,
        );
    }
}
