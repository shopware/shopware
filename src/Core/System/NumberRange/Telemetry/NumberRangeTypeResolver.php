<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * Resolves a number-range type technical name to the `number_range_type` label value
 * (see `metrics.mappings.number_range_type` in `telemetry.yaml`, extension and merge rules in
 * `src/Core/Framework/Telemetry/README.md`).
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class NumberRangeTypeResolver
{
    final public const MAPPING = 'number_range_type';

    public function __construct(private readonly LabelMappingRegistry $mappings)
    {
    }

    public function resolve(?string $technicalName): string
    {
        return $this->mappings->resolve(self::MAPPING, $technicalName ?? '');
    }
}
