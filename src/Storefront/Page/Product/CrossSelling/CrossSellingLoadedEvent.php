<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.4.0 - Use `\Shopware\Core\Content\Product\Events\ProductCrossSellingsLoadedEvent` instead
 */
class CrossSellingLoadedEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @var CrossSellingLoaderResult
     */
    private $result;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(CrossSellingLoaderResult $result, SalesChannelContext $salesChannelContext)
    {
        $this->result = $result;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getCrossSellingResult(): CrossSellingLoaderResult
    {
        return $this->result;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
