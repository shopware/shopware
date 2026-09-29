<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Extension\CheckoutCartAddOrderLineItemsExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

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
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly ExtensionDispatcher $extensions
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

        // scopes the order to the logged-in customer and the current sales channel
        $order = $this->orderRoute->load($request, $context, $criteria)->getOrders()->getEntities()->get($orderId);

        if (!$order instanceof OrderEntity) {
            throw CartException::orderNotFound($orderId);
        }

        $orderLineItems = $order->getLineItems();

        if ($orderLineItems === null || $orderLineItems->count() === 0) {
            throw CartException::lineItemNotFound($orderId);
        }

        $items = $this->extensions->publish(
            name: CheckoutCartAddOrderLineItemsExtension::NAME,
            extension: new CheckoutCartAddOrderLineItemsExtension($order, $cart, $context),
            function: $this->buildLineItems(...)
        );

        // null would make the add route read the posted items instead, which this route must never do
        return $this->cartItemAddRoute->add($request, $cart, $context, $items ?? []);
    }

    /**
     * @return list<LineItem>
     */
    private function buildLineItems(OrderEntity $order, Cart $cart, SalesChannelContext $context): array
    {
        $quantities = [];

        foreach ($order->getLineItems() ?? new OrderLineItemCollection() as $orderLineItem) {
            // re-adding promotion or credit items would grant them for free
            if ($orderLineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $productId = $orderLineItem->getReferencedId();

            if ($productId === null) {
                continue;
            }

            $quantities[$productId] = ($quantities[$productId] ?? 0) + $orderLineItem->getQuantity();
        }

        $items = [];

        foreach ($quantities as $productId => $quantity) {
            // the product id is what a normal add to cart posts, so a reorder stacks onto an existing cart row
            $items[] = $this->lineItemFactory->create([
                'id' => $productId,
                'referencedId' => $productId,
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'quantity' => $quantity,
            ], $context);
        }

        return $items;
    }
}
