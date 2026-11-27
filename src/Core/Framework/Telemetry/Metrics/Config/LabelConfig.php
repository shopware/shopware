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
        $policy = isset($definition['policy']) ? LabelPolicy::tryFrom($definition['policy']) : null;

        // a label's values list can come from two keys: a non-empty `allowed_values` is the complete,
        // final list and always wins; without one, the list is the union of all `add_allowed_values` contributions.
        // The config tree applies this precedence at container build, this method mirrors it.
        // An open label uses no list at all - both keys are inert there and stay in the raw config
        // solely for debug:config visibility
        $allowedValues = [];
        if ($policy !== LabelPolicy::OPEN) {
            $allowedValues = $definition['allowed_values'] ?? [];
            if ($allowedValues === []) {
                $allowedValues = $definition['add_allowed_values'] ?? [];
            }
        }

        return new self(
            allowedValues: $allowedValues === [] ? null : $allowedValues,
            policy: $policy,
        );
    }
}
