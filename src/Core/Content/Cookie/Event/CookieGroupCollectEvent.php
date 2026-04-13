<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Event;

use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class CookieGroupCollectEvent implements ShopwareSalesChannelEvent
{
    public function __construct(
        public CookieGroupCollection $cookieGroupCollection,
        public readonly Request $request,
        protected readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
