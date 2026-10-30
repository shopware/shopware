<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * Buckets a flow trigger event name ({@see \Shopware\Core\Content\Flow\Dispatching\StorableFlow::getName()},
 * e.g. `checkout.order.placed`) to the `trigger_group` label value (see `metrics.mappings.flow_trigger_group`
 * in `telemetry.yaml`, extension and merge rules in `src/Core/Framework/Telemetry/README.md`).
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('after-sales')]
class TriggerGroupResolver
{
    final public const MAPPING = 'flow_trigger_group';

    public function __construct(private readonly LabelMappingRegistry $mappings)
    {
    }

    public function resolve(string $eventName): string
    {
        return $this->mappings->resolve(self::MAPPING, $eventName);
    }
}
