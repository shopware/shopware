<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Dispatched before the line items derived from an order are added to the cart. Listeners may replace the
 * list to change what a reorder adds, for example to collapse bundle children back into a single line item.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class BeforeOrderLineItemsAddedToCartEvent implements ShopwareSalesChannelEvent, CartEvent
{
    /**
     * @param list<LineItem> $lineItems
     */
    public function __construct(
        protected array $lineItems,
        protected readonly OrderEntity $order,
        protected readonly Cart $cart,
        protected readonly SalesChannelContext $salesChannelContext
    ) {
    }

    /**
     * @return list<LineItem>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * @param list<LineItem> $lineItems
     */
    public function setLineItems(array $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
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
