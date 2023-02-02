<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ShopwareSalesChannelEvent extends ShopwareEvent
{
    public function getSalesChannelContext(): SalesChannelContext;
}
