<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('content')]
class NavigationLoadedEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var Tree
     */
    protected $navigation;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    public function __construct(
        Tree $navigation,
        SalesChannelContext $salesChannelContext
    ) {
        $this->navigation = $navigation;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getNavigation(): Tree
    {
        return $this->navigation;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
