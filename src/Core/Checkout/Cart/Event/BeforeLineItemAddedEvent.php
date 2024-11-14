<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class BeforeLineItemAddedEvent implements ShopwareSalesChannelEvent, CartEvent
{
    /**
     * @var LineItem
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $lineItem;

    /**
     * @var Cart
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cart;

    /**
     * @var SalesChannelContext
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannelContext;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $merged;

    public function __construct(
        LineItem $lineItem,
        Cart $cart,
        SalesChannelContext $salesChannelContext,
        bool $merged = false
    ) {
        $this->lineItem = $lineItem;
        $this->cart = $cart;
        $this->salesChannelContext = $salesChannelContext;
        $this->merged = $merged;
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
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function isMerged(): bool
    {
        return $this->merged;
    }
}
