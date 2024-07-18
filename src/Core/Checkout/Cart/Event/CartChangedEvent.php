<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CartChangedEvent extends Event implements CartEvent
{
    /**
     * @deprecated tag:v6.7.0 - Param $cart will be typed and readonly when implementing ShopwareSalesChannelEvent
     *
     * @var Cart
     */
    protected $cart;

    /**
     * @deprecated tag:v6.7.0 - Param $context will be renamed to $salesChannelContext when implementing ShopwareSalesChannelEvent
     *
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(Cart $cart, SalesChannelContext $context)
    {
        $this->cart = $cart;
        $this->context = $context;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @deprecated tag:v6.7.0 - Should actually return Context like the other events: Use getSalesChannelContext() instead
     */
    public function getContext(): SalesChannelContext
    {
        // TODO implements ShopwareSalesChannelEvent
        // return $this->salesChannelContext->getContext();
        return $this->getSalesChannelContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
