<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderProductAvailabilityRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderProductAvailabilityRoute::class)]
class OrderProductAvailabilityRouteTest extends TestCase
{
    public function testGetDecoratedReturnsTheInnerRoute(): void
    {
        $decorated = static::createStub(AbstractOrderRoute::class);
        $route = new OrderProductAvailabilityRoute($decorated, static::createStub(SalesChannelRepository::class), $this->config(), static::createStub(AbstractProductCloseoutFilterFactory::class));

        static::assertSame($decorated, $route->getDecorated());
    }

    public function testVisibilityIsResolvedWithASingleQueryForAllOrders(): void
    {
        $visibleId = Uuid::randomHex();
        $hiddenId = Uuid::randomHex();

        $orders = new OrderCollection([
            $this->createOrder([[LineItem::PRODUCT_LINE_ITEM_TYPE, $visibleId]]),
            $this->createOrder([[LineItem::PRODUCT_LINE_ITEM_TYPE, $hiddenId]]),
            $this->createOrder([[LineItem::PRODUCT_LINE_ITEM_TYPE, $visibleId]]),
        ]);

        $repository = static::createMock(SalesChannelRepository::class);
        $repository
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::callback(static function (Criteria $criteria) use ($visibleId, $hiddenId): bool {
                static::assertEqualsCanonicalizing([$visibleId, $hiddenId], array_values($criteria->getIds()));

                return true;
            }))
            ->willReturn($this->createIdSearchResult([$visibleId]));

        $this->load($repository, $orders);

        foreach ($orders as $order) {
            $lineItems = $order->getLineItems();
            static::assertNotNull($lineItems);

            foreach ($lineItems as $lineItem) {
                $extension = $lineItem->getExtension(OrderProductAvailabilityRoute::LINE_ITEM_EXTENSION);
                static::assertInstanceOf(ArrayStruct::class, $extension);
                static::assertSame($lineItem->getProductId() === $visibleId, $extension->get('visible'));
            }
        }
    }

    public function testDeletedProductsAreNeverQueriedAndAreNotVisible(): void
    {
        $order = $this->createOrder([[LineItem::PRODUCT_LINE_ITEM_TYPE, null]]);

        $repository = static::createMock(SalesChannelRepository::class);
        $repository->expects($this->never())->method('searchIds');

        $this->load($repository, new OrderCollection([$order]));

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);

        $extension = $lineItems->first()?->getExtension(OrderProductAvailabilityRoute::LINE_ITEM_EXTENSION);
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertFalse($extension->get('visible'));
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

        $this->load($repository, new OrderCollection([$order]));

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);

        $promotion = $lineItems->filterByType(LineItem::PROMOTION_LINE_ITEM_TYPE)->first();
        static::assertNotNull($promotion);
        static::assertNull($promotion->getExtension(OrderProductAvailabilityRoute::LINE_ITEM_EXTENSION));
    }

    /**
     * @param SalesChannelRepository<ProductCollection> $repository
     */
    private function load(SalesChannelRepository $repository, OrderCollection $orders): void
    {
        $context = Generator::generateSalesChannelContext();

        $decorated = static::createStub(AbstractOrderRoute::class);
        $decorated->method('load')->willReturn(new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                $orders->count(),
                $orders,
                null,
                new Criteria(),
                $context->getContext()
            )
        ));

        (new OrderProductAvailabilityRoute($decorated, $repository, $this->config(), static::createStub(AbstractProductCloseoutFilterFactory::class)))
            ->load(new Request(), $context, new Criteria());
    }

    private function config(): StaticSystemConfigService
    {
        return new StaticSystemConfigService(['core.listing.hideCloseoutProductsWhenOutOfStock' => false]);
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
