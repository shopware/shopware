<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderLineItemsAddRoute;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartOrderLineItemsAddRoute::class)]
class CartOrderLineItemsAddRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        static::expectExceptionObject(new DecorationPatternException(CartOrderLineItemsAddRoute::class));

        (new CartOrderLineItemsAddRoute(
            static::createStub(AbstractOrderRoute::class),
            static::createStub(AbstractCartItemAddRoute::class),
            static::createStub(EventDispatcherInterface::class)
        ))->getDecorated();
    }

    public function testUnknownOrderThrows(): void
    {
        $orderId = Uuid::randomHex();

        $route = new CartOrderLineItemsAddRoute(
            $this->createOrderRoute(new OrderCollection()),
            static::createStub(AbstractCartItemAddRoute::class),
            new EventDispatcher()
        );

        static::expectExceptionObject(CartException::orderNotFound($orderId));

        $route->add($orderId, new Request(), new Cart('token'), Generator::generateSalesChannelContext());
    }

    public function testOrderWithoutLineItemsThrows(): void
    {
        $orderId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setUniqueIdentifier($orderId);
        $order->setId($orderId);
        $order->setLineItems(new OrderLineItemCollection());

        $route = new CartOrderLineItemsAddRoute(
            $this->createOrderRoute(new OrderCollection([$order])),
            static::createStub(AbstractCartItemAddRoute::class),
            new EventDispatcher()
        );

        static::expectExceptionObject(CartException::lineItemNotFound($orderId));

        $route->add($orderId, new Request(), new Cart('token'), Generator::generateSalesChannelContext());
    }

    public function testOnlyProductLineItemsAreAddedAndAreStrippedOfTheOriginalId(): void
    {
        $orderId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setUniqueIdentifier($orderId);
        $order->setId($orderId);
        $order->setLineItems(new OrderLineItemCollection([
            $this->createOrderLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 1),
            $this->createOrderLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex(), 2),
            $this->createOrderLineItem(LineItem::CREDIT_LINE_ITEM_TYPE, Uuid::randomHex(), 3),
        ]));

        $cartItemAddRoute = static::createMock(AbstractCartItemAddRoute::class);
        $cartItemAddRoute
            ->expects($this->once())
            ->method('add')
            ->with(
                static::isInstanceOf(Request::class),
                static::isInstanceOf(Cart::class),
                static::anything(),
                static::callback(static function (?array $items): bool {
                    static::assertIsArray($items);
                    static::assertCount(1, $items, 'only product line items may be re-added');

                    $item = $items[0];
                    static::assertInstanceOf(LineItem::class, $item);
                    static::assertSame(LineItem::PRODUCT_LINE_ITEM_TYPE, $item->getType());
                    static::assertTrue($item->isStackable());
                    static::assertFalse(
                        $item->hasExtension(OrderConverter::ORIGINAL_ID),
                        'the original order line item id must not leak into the cart'
                    );

                    return true;
                })
            )
            ->willReturn(new CartResponse(new Cart('token')));

        $route = new CartOrderLineItemsAddRoute(
            $this->createOrderRoute(new OrderCollection([$order])),
            $cartItemAddRoute,
            new EventDispatcher()
        );

        $route->add($orderId, new Request(), new Cart('token'), Generator::generateSalesChannelContext());
    }

    public function testCriteriaIsScopedToTheOrderAndItsLineItems(): void
    {
        $orderId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setUniqueIdentifier($orderId);
        $order->setId($orderId);
        $order->setLineItems(new OrderLineItemCollection([
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

        $route = new CartOrderLineItemsAddRoute($orderRoute, $cartItemAddRoute, new EventDispatcher());

        $route->add($orderId, new Request(), new Cart('token'), Generator::generateSalesChannelContext());
    }

    private function createOrderLineItem(string $type, string $productId, int $position = 1): OrderLineItemEntity
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
        $lineItem->setQuantity(2);
        $lineItem->setGood(true);
        $lineItem->setRemovable(true);
        $lineItem->setStackable(false);
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
