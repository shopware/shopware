<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;

class CmsSlotDataResolverEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    private CmsSlotsDataResolver $resolver;

    private SalesChannelContext  $salesChannelContext;

    public function __construct(CmsSlotsDataResolver $resolver, SalesChannelContext $salesChannelContext)
    {
        $this->resolver            = $resolver;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getResolver(): CmsSlotsDataResolver
    {
        return $this->resolver;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
