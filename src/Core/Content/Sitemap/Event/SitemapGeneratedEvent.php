<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('sales-channel')]
class SitemapGeneratedEvent extends Event implements ShopwareEvent
{
    public function __construct(private readonly SalesChannelContext $context)
    {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
