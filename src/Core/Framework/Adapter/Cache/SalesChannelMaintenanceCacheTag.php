<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelMaintenanceCacheTag
{
    public static function build(string $salesChannelId): string
    {
        return 'sales-channel-maintenance-' . $salesChannelId;
    }
}
