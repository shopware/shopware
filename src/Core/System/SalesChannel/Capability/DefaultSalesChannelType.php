<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Capability;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * Misses the types extensions register; consumers go through `SalesChannelTypeCapabilityRegistry`.
 *
 * @internal
 */
#[Package('discovery')]
enum DefaultSalesChannelType: string
{
    case STOREFRONT = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
    case HEADLESS = Defaults::SALES_CHANNEL_TYPE_API;
    case PRODUCT_COMPARISON = Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON;

    public function isTransactional(): bool
    {
        return match ($this) {
            self::STOREFRONT, self::HEADLESS => true,
            self::PRODUCT_COMPARISON => false,
        };
    }
}
