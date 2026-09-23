<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Event\OrderProductAvailabilitySubscriber;
use Shopware\Storefront\Page\Account\Order\AccountOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderProductAvailabilitySubscriber::class)]
class OrderProductAvailabilitySubscriberTest extends TestCase
{
    public function testSubscribedEventsCoverEveryOrderRenderingPage(): void
    {
        static::assertSame(
            [
                'Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent',
                'Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent',
                'Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent',
            ],
            array_keys(OrderProductAvailabilitySubscriber::getSubscribedEvents())
        );
    }

    public function testAvailabilityIsResolvedWithASingleQueryForAllOrdersOfThePage(): void
    {
        $availableId = Uuid::randomHex();
        $unavailableId = Uuid::randomHex();

        $orders = new OrderCollection([
            $this->createOrder([[LineItem::PRODUCT_LINE_ITEM_TYPE, $availableId]]),
            $this->createOrder([[LineItem::PRODUCT_LINE_ITEM_TYPE, $unavailableId]]),
            $this->createOrder([[LineItem::PRODUCT_LINE_ITEM_TYPE, $availableId]]),
        ]);

        $repository = static::createMock(SalesChannelRepository::class);
        $repository
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::callback(static function (Criteria $criteria) use ($availableId, $unavailableId): bool {
                static::assertEqualsCanonicalizing([$availableId, $unavailableId], array_values($criteria->getIds()));

                return true;
            }))
            ->willReturn($this->createIdSearchResult([$availableId]));

        $this->dispatch($repository, $orders);

        foreach ($orders as $order) {
            $lineItems = $order->getLineItems();
            static::assertNotNull($lineItems);

            foreach ($lineItems as $lineItem) {
                $extension = $lineItem->getExtension(OrderProductAvailabilitySubscriber::LINE_ITEM_EXTENSION);
                static::assertInstanceOf(ArrayStruct::class, $extension);
                static::assertSame($lineItem->getProductId() === $availableId, $extension->get('available'));
            }
        }
    }

    public function testOrderIsReorderableWhenAtLeastOneProductIsAvailable(): void
    {
        $availableId = Uuid::randomHex();
        $unavailableId = Uuid::randomHex();

        $mixed = $this->createOrder([
            [LineItem::PRODUCT_LINE_ITEM_TYPE, $availableId],
            [LineItem::PRODUCT_LINE_ITEM_TYPE, $unavailableId],
        ]);
        $none = $this->createOrder([[LineItem::PRODUCT_LINE_ITEM_TYPE, $unavailableId]]);

        $repository = static::createStub(SalesChannelRepository::class);
        $repository->method('searchIds')->willReturn($this->createIdSearchResult([$availableId]));

        $this->dispatch($repository, new OrderCollection([$mixed, $none]));

        static::assertTrue($this->reorderable($mixed));
        static::assertFalse($this->reorderable($none));
    }

    public function testDeletedProductsAreNeverQueriedAndCountAsUnavailable(): void
    {
        $order = $this->createOrder([[LineItem::PRODUCT_LINE_ITEM_TYPE, null]]);

        $repository = static::createMock(SalesChannelRepository::class);
        $repository->expects($this->never())->method('searchIds');

        $this->dispatch($repository, new OrderCollection([$order]));

        static::assertFalse($this->reorderable($order));
    }

    public function testNonProductLineItemsAreIgnored(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrder([
            [LineItem::PRODUCT_LINE_ITEM_TYPE, $productId],
            [LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex()],
        ]);

        $repository = static::createMock(SalesChannelRepository::class);
        $repository
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::callback(static function (Criteria $criteria) use ($productId): bool {
                static::assertSame([$productId], $criteria->getIds());

                return true;
            }))
            ->willReturn($this->createIdSearchResult([$productId]));

        $this->dispatch($repository, new OrderCollection([$order]));

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);

        $promotion = $lineItems->filterByType(LineItem::PROMOTION_LINE_ITEM_TYPE)->first();
        static::assertNotNull($promotion);
        static::assertNull($promotion->getExtension(OrderProductAvailabilitySubscriber::LINE_ITEM_EXTENSION));
    }

    /**
     * @param SalesChannelRepository<ProductCollection> $repository
     */
    private function dispatch(SalesChannelRepository $repository, OrderCollection $orders): void
    {
        $context = Generator::generateSalesChannelContext();

        $page = new AccountOrderPage();
        $page->setOrders(new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            $orders->count(),
            $orders,
            null,
            new Criteria(),
            $context->getContext()
        ));

        (new OrderProductAvailabilitySubscriber($repository))->onAccountOrderPageLoaded(
            new AccountOrderPageLoadedEvent($page, $context, new Request())
        );
    }

    private function reorderable(OrderEntity $order): bool
    {
        $extension = $order->getExtension(OrderProductAvailabilitySubscriber::ORDER_EXTENSION);
        static::assertInstanceOf(ArrayStruct::class, $extension);

        return (bool) $extension->get('available');
    }

    /**
     * @param list<array{0: string, 1: string|null}> $lineItems
     */
    private function createOrder(array $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $id = Uuid::randomHex();
        $order->setId($id);
        $order->setUniqueIdentifier($id);

        $collection = new OrderLineItemCollection();
        foreach ($lineItems as $position => [$type, $productId]) {
            $lineItem = new OrderLineItemEntity();
            $lineItemId = Uuid::randomHex();
            $lineItem->setId($lineItemId);
            $lineItem->setUniqueIdentifier($lineItemId);
            $lineItem->setType($type);
            $lineItem->setProductId($productId);
            $lineItem->setReferencedId($productId);
            $lineItem->setPosition($position + 1);
            $collection->add($lineItem);
        }

        $order->setLineItems($collection);

        return $order;
    }

    /**
     * @param list<string> $ids
     */
    private function createIdSearchResult(array $ids): IdSearchResult
    {
        return IdSearchResult::fromIds($ids, new Criteria(), Context::createDefaultContext());
    }
}
