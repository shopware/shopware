<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.4.0 - this event will be removed in the future and is replaced with `BeforeLineItemQuantityChangedEvent`
 */
class LineItemQuantityChangedEvent extends Event /*implements ShopwareSalesChannelEvent*/
{
    /**
     * @var LineItem
     */
    protected $lineItem;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(LineItem $lineItem, Cart $cart, SalesChannelContext $context)
    {
        $this->lineItem = $lineItem;
        $this->cart = $cart;
        $this->context = $context;
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @deprecated tag:v6.4.0 - Will return Shopware\Core\Framework\Context instead
     */
    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
