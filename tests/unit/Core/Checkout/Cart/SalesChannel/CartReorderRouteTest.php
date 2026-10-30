<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Extension\CheckoutCartCollectReorderLineItemsExtension;
use Shopware\Core\Checkout\Cart\Extension\CheckoutCartReorderExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartReorderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartReorderRoute::class)]
class CartReorderRouteTest extends TestCase
{
    public function testTheRouteIsPublishedAsAnExtension(): void
    {
        $orderId = Uuid::randomHex();
        $request = new Request();
        $cart = new Cart('token');
        $context = Generator::generateSalesChannelContext();

        $seen = null;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            CheckoutCartReorderExtension::onPre(),
            static function (CheckoutCartReorderExtension $extension) use (&$seen): void {
                $seen = $extension;
                $extension->result = new CartResponse(new Cart('replaced'));
                $extension->stopPropagation();
            }
        );

        $orderRoute = static::createMock(AbstractOrderRoute::class);
        $orderRoute->expects($this->never())->method('load');

        $response = $this->createRoute($orderRoute, static::createStub(AbstractCartItemAddRoute::class), $dispatcher)
            ->reorder($orderId, $request, $cart, $context);

        static::assertInstanceOf(CheckoutCartReorderExtension::class, $seen);
        static::assertSame($orderId, $seen->orderId);
        static::assertSame($request, $seen->request);
        static::assertSame($cart, $seen->cart);
        static::assertSame($context, $seen->context);
        static::assertSame('replaced', $response->getCart()->getToken());
    }

    public function testUnknownOrderThrows(): void
    {
        $orderId = Uuid::randomHex();

        $route = $this->createRoute(
            $this->createOrderRoute(new OrderCollection()),
            static::createStub(AbstractCartItemAddRoute::class)
        );

        static::expectExceptionObject(CartException::orderNotFound($orderId));

        $route->reorder($orderId, new Request(), new Cart('token'), Generator::generateSalesChannelContext());
    }

    public function testOrderWithoutLineItemsThrows(): void
    {
        $orderId = Uuid::randomHex();

        $route = $this->createRoute(
            $this->createOrderRoute(new OrderCollection([$this->createOrder($orderId, new OrderLineItemCollection())])),
            static::createStub(AbstractCartItemAddRoute::class)
        );

        static::expectExceptionObject(CartException::lineItemNotFound($orderId));

        $route->reorder($orderId, new Request(), new Cart('token'), Generator::generateSalesChannelContext());
    }

    public function testOnlyProductsAreRebuiltFromTheOrder(): void
    {
        $orderId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $order = $this->createOrder($orderId, new OrderLineItemCollection([
            $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 2, 1),
            $this->createOrderLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex(), 1, 2),
            $this->createOrderLineItem(LineItem::CREDIT_LINE_ITEM_TYPE, Uuid::randomHex(), 1, 3),
        ]));

        $items = $this->capture($order, $orderId);

        static::assertCount(1, $items, 'only product line items may be re-added');

        $item = $items[0];
        static::assertSame(LineItem::PRODUCT_LINE_ITEM_TYPE, $item->getType());
        static::assertSame($productId, $item->getId(), 'the cart id must be the product id, as a normal add to cart posts it');
        static::assertSame($productId, $item->getReferencedId());
        static::assertSame(2, $item->getQuantity());
        static::assertTrue($item->isStackable());
        static::assertTrue($item->isRemovable());
        static::assertSame([], $item->getPayload(), 'the persisted order payload must not travel into the cart');
        static::assertCount(0, $item->getChildren(), 'persisted children must not travel into the cart');
    }

    public function testQuantitiesOfTheSameProductAreAggregated(): void
    {
        $orderId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $order = $this->createOrder($orderId, new OrderLineItemCollection([
            $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 2, 1),
            $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 3, 2),
        ]));

        $items = $this->capture($order, $orderId);

        static::assertCount(1, $items);
        static::assertSame(5, $items[0]->getQuantity());
    }

    public function testLineItemsWithoutAReferencedIdAreSkipped(): void
    {
        $orderId = Uuid::randomHex();

        $deleted = $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1, 1);
        $deleted->setReferencedId(null);

        $order = $this->createOrder($orderId, new OrderLineItemCollection([
            $deleted,
            $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1, 2),
        ]));

        static::assertCount(1, $this->capture($order, $orderId));
    }

    public function testCriteriaIsScopedToTheOrderAndItsLineItems(): void
    {
        $orderId = Uuid::randomHex();

        $order = $this->createOrder($orderId, new OrderLineItemCollection([
            $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex()),
        ]));

        $orderRoute = static::createMock(AbstractOrderRoute::class);
        $orderRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::isInstanceOf(Request::class),
                static::anything(),
                static::callback(static function (Criteria $criteria) use ($orderId): bool {
                    static::assertSame([$orderId], $criteria->getIds());
                    static::assertTrue($criteria->hasAssociation('lineItems'));
                    static::assertFalse(
                        $criteria->getAssociation('lineItems')->hasAssociation('downloads'),
                        'downloads are not needed and would be converted into the cart'
                    );

                    return true;
                })
            )
            ->willReturn($this->createOrderRouteResponse(new OrderCollection([$order])));

        $cartItemAddRoute = static::createStub(AbstractCartItemAddRoute::class);
        $cartItemAddRoute->method('add')->willReturn(new CartResponse(new Cart('token')));

        $this->createRoute($orderRoute, $cartItemAddRoute)
            ->reorder($orderId, new Request(), new Cart('token'), Generator::generateSalesChannelContext());
    }

    public function testAListenerCanAppendItsOwnLineItemsOnPost(): void
    {
        $orderId = Uuid::randomHex();
        $own = new LineItem(Uuid::randomHex(), 'my-own-type');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            CheckoutCartCollectReorderLineItemsExtension::onPost(),
            static function (CheckoutCartCollectReorderLineItemsExtension $extension) use ($own): void {
                $extension->result = [...$extension->result, $own];
            }
        );

        $order = $this->createOrder($orderId, new OrderLineItemCollection([
            $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex()),
        ]));

        $items = $this->capture($order, $orderId, $dispatcher);

        static::assertCount(2, $items);
        static::assertSame($own, $items[1]);
    }

    public function testAListenerCanReplaceTheWholeListOnPre(): void
    {
        $orderId = Uuid::randomHex();
        $own = new LineItem(Uuid::randomHex(), 'my-own-type');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            CheckoutCartCollectReorderLineItemsExtension::onPre(),
            static function (CheckoutCartCollectReorderLineItemsExtension $extension) use ($own): void {
                $extension->result = [$own];
                $extension->stopPropagation();
            }
        );

        $order = $this->createOrder($orderId, new OrderLineItemCollection([
            $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex()),
        ]));

        static::assertSame([$own], $this->capture($order, $orderId, $dispatcher));
    }

    public function testStoppingWithoutAResultAddsNothingInsteadOfReadingTheRequest(): void
    {
        $orderId = Uuid::randomHex();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            CheckoutCartCollectReorderLineItemsExtension::onPre(),
            static fn (CheckoutCartCollectReorderLineItemsExtension $extension) => $extension->stopPropagation()
        );

        $order = $this->createOrder($orderId, new OrderLineItemCollection([
            $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex()),
        ]));

        static::assertSame([], $this->capture($order, $orderId, $dispatcher));
    }

    /**
     * @return list<LineItem>
     */
    private function capture(OrderEntity $order, string $orderId, ?EventDispatcher $dispatcher = null): array
    {
        $captured = null;

        $cartItemAddRoute = static::createMock(AbstractCartItemAddRoute::class);
        $cartItemAddRoute
            ->expects($this->once())
            ->method('add')
            ->with(
                static::isInstanceOf(Request::class),
                static::isInstanceOf(Cart::class),
                static::anything(),
                static::callback(static function (?array $items) use (&$captured): bool {
                    $captured = $items;

                    return true;
                })
            )
            ->willReturn(new CartResponse(new Cart('token')));

        $this->createRoute($this->createOrderRoute(new OrderCollection([$order])), $cartItemAddRoute, $dispatcher)
            ->reorder($orderId, new Request(), new Cart('token'), Generator::generateSalesChannelContext());

        static::assertIsArray($captured, 'the add route must never be handed null');

        return array_values($captured);
    }

    private function createRoute(
        AbstractOrderRoute $orderRoute,
        AbstractCartItemAddRoute $cartItemAddRoute,
        ?EventDispatcher $dispatcher = null
    ): CartReorderRoute {
        return new CartReorderRoute(
            $orderRoute,
            $cartItemAddRoute,
            new LineItemFactoryRegistry(
                [new ProductLineItemFactory(new PriceDefinitionFactory())],
                static::createStub(DataValidator::class),
                new EventDispatcher()
            ),
            new ExtensionDispatcher($dispatcher ?? new EventDispatcher())
        );
    }

    private function createOrder(string $orderId, OrderLineItemCollection $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setUniqueIdentifier($orderId);
        $order->setId($orderId);
        $order->setLineItems($lineItems);

        return $order;
    }

    private function createOrderLineItem(string $type, string $productId, int $quantity = 2, int $position = 1): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $id = Uuid::randomHex();
        $lineItem->setId($id);
        $lineItem->setUniqueIdentifier($id);
        $lineItem->setIdentifier($productId);
        $lineItem->setReferencedId($productId);
        $lineItem->setProductId($productId);
        $lineItem->setType($type);
        $lineItem->setLabel('line item');
        $lineItem->setQuantity($quantity);
        $lineItem->setGood(true);
        $lineItem->setRemovable(true);
        $lineItem->setStackable(false);
        $lineItem->setPayload(['bundleId' => Uuid::randomHex()]);
        $lineItem->setPosition($position);

        return $lineItem;
    }

    private function createOrderRoute(OrderCollection $orders): AbstractOrderRoute
    {
        $orderRoute = static::createStub(AbstractOrderRoute::class);
        $orderRoute->method('load')->willReturn($this->createOrderRouteResponse($orders));

        return $orderRoute;
    }

    private function createOrderRouteResponse(OrderCollection $orders): OrderRouteResponse
    {
        return new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                $orders->count(),
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );
    }
}
