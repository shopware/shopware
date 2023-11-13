<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class LineItemRemovedEvent extends Event implements ShopwareSalesChannelEvent
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

    public function __construct(
        LineItem $lineItem,
        Cart $cart,
        SalesChannelContext $context
    ) {
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

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
