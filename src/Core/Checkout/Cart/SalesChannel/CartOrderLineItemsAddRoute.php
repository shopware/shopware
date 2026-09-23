<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Event\BeforeOrderLineItemsAddedToCartEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\Transformer\LineItemTransformer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CartOrderLineItemsAddRoute extends AbstractCartOrderLineItemsAddRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractOrderRoute $orderRoute,
        private readonly AbstractCartItemAddRoute $cartItemAddRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractCartOrderLineItemsAddRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/checkout/cart/line-item/order/{orderId}',
        name: 'store-api.checkout.cart.line-item.order.add',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function add(string $orderId, Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');

        // the order route scopes the order to the logged-in customer and the current sales channel
        $order = $this->orderRoute->load($request, $context, $criteria)->getOrders()->getEntities()->get($orderId);

        if (!$order instanceof OrderEntity) {
            throw CartException::orderNotFound($orderId);
        }

        $orderLineItems = $order->getLineItems();

        if ($orderLineItems === null || $orderLineItems->count() === 0) {
            throw CartException::lineItemNotFound($orderId);
        }

        $items = [];

        foreach (LineItemTransformer::transformFlatToNested($orderLineItems) as $lineItem) {
            // promotion, credit and discount items are granted by the cart pipeline, re-adding them would give them away for free
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            // the id identifies the order line item, keeping it would let the next checkout overwrite the original order
            $this->removeOriginalIdExtension($lineItem);

            // the replaced form posted stackable=1 for every item, so a reorder never failed on a non-stackable item
            $lineItem->setStackable(true);

            $items[] = $lineItem;
        }

        $event = new BeforeOrderLineItemsAddedToCartEvent($items, $order, $cart, $context);
        $this->eventDispatcher->dispatch($event);

        // unavailable products are removed by the product cart processor, which reports them as cart errors
        return $this->cartItemAddRoute->add($request, $cart, $context, $event->getLineItems());
    }

    private function removeOriginalIdExtension(LineItem $lineItem): void
    {
        $lineItem->removeExtension(OrderConverter::ORIGINAL_ID);

        foreach ($lineItem->getChildren() as $child) {
            $this->removeOriginalIdExtension($child);
        }
    }
}
