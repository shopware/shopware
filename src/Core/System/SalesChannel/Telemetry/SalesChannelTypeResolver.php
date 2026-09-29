<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Telemetry;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * Maps a sales channel type id to a small, bounded label value, so the (plugin-extensible) set of
 * sales channel types does not blow up the metric label cardinality.
 *
 * Owns its bounded output set (closed map, `other` as default), so the consuming metric labels may use
 * `policy: open`. Known outputs: storefront, api, product_comparison, agentic_commerce, other.
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
    /**
     * @var array<string, string>
     */
    private const TYPES = [
        Defaults::SALES_CHANNEL_TYPE_STOREFRONT => 'storefront',
        Defaults::SALES_CHANNEL_TYPE_API => 'api',
        Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON => 'product_comparison',
        Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE => 'agentic_commerce',
    ];

    public function resolve(string $typeId): string
    {
        return self::TYPES[$typeId] ?? 'other';
    }
}
