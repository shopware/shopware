<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Dispatched once per add to the cart, after all its line items are in the cart and before the cart is calculated.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class LineItemsAddedEvent implements ShopwareSalesChannelEvent, CartEvent
{
    /**
     * @param array<LineItem> $lineItems
     */
    public function __construct(
        private readonly array $lineItems,
        private readonly Cart $cart,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    /**
     * @return array<LineItem>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
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
}
