<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Capability;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class HeadlessSalesChannelTypeCapabilities extends AbstractSalesChannelTypeCapabilities
{
    public function getSalesChannelTypeId(): string
    {
        return Defaults::SALES_CHANNEL_TYPE_API;
    }

    public function isTransactional(): bool
    {
        return true;
    }
}
