<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Config;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;

/**
 * @internal
 *
 * @phpstan-import-type MetricTypeValues from Type
 * @phpstan-import-type LabelDefinition from LabelConfig
 *
 * @phpstan-type MetricDefinition array{
 *    type: MetricTypeValues,
 *    description: string,
 *    unit?: string,
 *    parameters?: array<string, mixed>,
 *    enabled: bool,
 *    labels?: array<string, LabelDefinition>
 * }
 */
#[Package('framework')]
readonly class MetricConfig
{
    /**
     * @param array<string, mixed> $parameters
     * @param array<string, LabelConfig> $labels
     */
    public function __construct(
        public string $name,
        public string $description,
        public Type $type,
        public bool $enabled,
        public array $parameters = [],
        public array $labels = [],
        public ?string $unit = null,
    ) {
    }

    /**
     * @param MetricDefinition $definition
     */
    public static function fromDefinition(string $name, array $definition): self
    {
        $labels = [];
        foreach ($definition['labels'] ?? [] as $labelName => $labelDefinition) {
            $labels[$labelName] = LabelConfig::fromDefinition($labelDefinition);
        }

        return new self(
            name: $name,
            description: $definition['description'],
            type: Type::from($definition['type']),
            enabled: $definition['enabled'],
            parameters: $definition['parameters'] ?? [],
            labels: $labels,
            unit: $definition['unit'] ?? null
        );
    }
}
