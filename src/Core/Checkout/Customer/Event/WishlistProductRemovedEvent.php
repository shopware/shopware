<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class WishlistProductRemovedEvent implements ShopwareSalesChannelEvent
{
    protected string $wishlistId;

    protected string $productId;

    protected SalesChannelContext $context;

    public function __construct(string $wishlistId, string $productId, SalesChannelContext $context)
    {
        $this->wishlistId = $wishlistId;
        $this->productId = $productId;
        $this->context = $context;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
