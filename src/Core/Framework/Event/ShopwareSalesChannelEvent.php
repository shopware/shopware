<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('core')]
interface ShopwareSalesChannelEvent extends ShopwareEvent
{
    public function getSalesChannelContext(): SalesChannelContext;
}
