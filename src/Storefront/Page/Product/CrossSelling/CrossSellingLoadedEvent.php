<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CrossSellingLoadedEvent extends Event implements ShopwareEvent
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
