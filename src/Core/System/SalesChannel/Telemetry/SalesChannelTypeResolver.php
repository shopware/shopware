<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * Resolves a sales channel type id (core ids: `Defaults::SALES_CHANNEL_TYPE_*`) to the `sales_channel_type`
 * label value (see `metrics.mappings.sales_channel_type` in `telemetry.yaml`, extension and merge rules in
 * `src/Core/Framework/Telemetry/README.md`).
 *
 * Shared resolver — used by the cart calculation and order placed collectors.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('checkout')]
class SalesChannelTypeResolver
{
    final public const MAPPING = 'sales_channel_type';

    public function __construct(private readonly LabelMappingRegistry $mappings)
    {
    }

    public function resolve(string $typeId): string
    {
        return $this->mappings->resolve(self::MAPPING, $typeId);
    }
}
