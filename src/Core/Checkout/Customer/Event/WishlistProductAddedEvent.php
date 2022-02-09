<?php

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class WishlistProductAddedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var string
     */
    protected string $wishlistId;

    /**
     * @var string
     */
    protected string $productId;

    /**
     * @var SalesChannelContext
     */
    protected SalesChannelContext $context;

    /**
     * @param string $wishlistId
     * @param string $productId
     * @param SalesChannelContext $context
     */
    public function __construct(string $wishlistId, string $productId, SalesChannelContext $context)
    {
        $this->wishlistId = $wishlistId;
        $this->productId = $productId;
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    /**
     * @return SalesChannelContext
     */
    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
