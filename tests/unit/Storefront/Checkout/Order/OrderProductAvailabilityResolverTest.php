<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Checkout\Order\OrderProductAvailabilityResolver;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderProductAvailabilityResolver::class)]
class OrderProductAvailabilityResolverTest extends TestCase
{
    public function testOnlyReachableProductsAreFlaggedVisible(): void
    {
        $visibleId = Uuid::randomHex();
        $hiddenId = Uuid::randomHex();

        $order = $this->createOrder([
            $this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $visibleId),
            $this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $hiddenId),
        ]);

        $this->createResolver([$visibleId])->addAvailability([$order], Generator::generateSalesChannelContext());

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);

        foreach ($lineItems as $lineItem) {
            $extension = $lineItem->getExtension(OrderProductAvailabilityResolver::LINE_ITEM_EXTENSION);
            static::assertInstanceOf(ArrayStruct::class, $extension);
            static::assertSame($lineItem->getProductId() === $visibleId, $extension->get('visible'));
        }
    }

    public function testDeletedProductsAreNeverQueriedAndAreNotVisible(): void
    {
        $lineItem = $this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, null);
        $order = $this->createOrder([$lineItem]);

        $route = static::createMock(AbstractProductListRoute::class);
        $route->expects($this->never())->method('load');

        $this->createResolver([], $route)->addAvailability([$order], Generator::generateSalesChannelContext());

        $extension = $lineItem->getExtension(OrderProductAvailabilityResolver::LINE_ITEM_EXTENSION);
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertFalse($extension->get('visible'));
    }

    public function testNonProductLineItemsAreIgnored(): void
    {
        $promotion = $this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex());
        $order = $this->createOrder([$promotion]);

        $route = static::createMock(AbstractProductListRoute::class);
        // an order without products must not run an unfiltered, unlimited criteria over the catalogue
        $route->expects($this->never())->method('load');

        $this->createResolver([], $route)->addAvailability([$order], Generator::generateSalesChannelContext());

        static::assertNull($promotion->getExtension(OrderProductAvailabilityResolver::LINE_ITEM_EXTENSION));
    }

    public function testAllOrdersAreResolvedWithASingleCall(): void
    {
        $first = Uuid::randomHex();
        $second = Uuid::randomHex();

        $orders = [
            $this->createOrder([$this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $first)]),
            $this->createOrder([$this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $second)]),
            $this->createOrder([$this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $first)]),
        ];

        $route = static::createMock(AbstractProductListRoute::class);
        $route
            ->expects($this->once())
            ->method('load')
            ->with(static::callback(static function (Criteria $criteria) use ($first, $second): bool {
                static::assertEqualsCanonicalizing([$first, $second], array_values($criteria->getIds()));
                static::assertSame(['id'], $criteria->getFields(), 'the lookup must stay a partial load');

                return true;
            }))
            ->willReturn($this->createResponse([$first]));

        $this->createResolver([$first], $route)->addAvailability($orders, Generator::generateSalesChannelContext());

        static::assertTrue($this->firstExtension($orders[0])->get('visible'));
        static::assertFalse($this->firstExtension($orders[1])->get('visible'));
    }

    public function testTheCloseoutFilterIsOnlyAddedWhenTheConfigIsOn(): void
    {
        $productId = Uuid::randomHex();
        $order = $this->createOrder([$this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $productId)]);

        $route = static::createMock(AbstractProductListRoute::class);
        $route
            ->expects($this->once())
            ->method('load')
            ->with(static::callback(static function (Criteria $criteria): bool {
                static::assertCount(1, $criteria->getFilters());
                static::assertInstanceOf(ProductCloseoutFilter::class, $criteria->getFilters()[0]);

                return true;
            }))
            ->willReturn($this->createResponse([$productId]));

        $resolver = $this->createResolver([$productId], $route, closeoutHidden: true);
        $resolver->addAvailability([$order], Generator::generateSalesChannelContext());
    }

    private function firstExtension(OrderEntity $order): ArrayStruct
    {
        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);

        $extension = $lineItems->first()?->getExtension(OrderProductAvailabilityResolver::LINE_ITEM_EXTENSION);
        static::assertInstanceOf(ArrayStruct::class, $extension);

        return $extension;
    }

    /**
     * @param list<string> $visibleIds
     */
    private function createResolver(
        array $visibleIds,
        ?AbstractProductListRoute $route = null,
        bool $closeoutHidden = false
    ): OrderProductAvailabilityResolver {
        if ($route === null) {
            $route = static::createStub(AbstractProductListRoute::class);
            $route->method('load')->willReturn($this->createResponse($visibleIds));
        }

        $closeoutFilterFactory = static::createStub(AbstractProductCloseoutFilterFactory::class);
        $closeoutFilterFactory->method('create')->willReturn(new ProductCloseoutFilter());

        $config = new StaticSystemConfigService([
            'core.listing.hideCloseoutProductsWhenOutOfStock' => $closeoutHidden,
        ]);

        return new OrderProductAvailabilityResolver($route, $config, $closeoutFilterFactory);
    }

    /**
     * @param list<string> $visibleIds
     */
    private function createResponse(array $visibleIds): ProductListResponse
    {
        $products = new ProductCollection();

        foreach ($visibleIds as $id) {
            $product = new ProductEntity();
            $product->setId($id);
            $product->setUniqueIdentifier($id);
            $products->add($product);
        }

        return new ProductListResponse(new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            $products->count(),
            $products,
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function createOrder(array $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $id = Uuid::randomHex();
        $order->setId($id);
        $order->setUniqueIdentifier($id);
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private function createLineItem(string $type, ?string $productId): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $id = Uuid::randomHex();
        $lineItem->setId($id);
        $lineItem->setUniqueIdentifier($id);
        $lineItem->setType($type);
        $lineItem->setLabel('line item');
        $lineItem->setQuantity(1);
        $lineItem->setGood(true);
        $lineItem->setProductId($productId);
        $lineItem->setReferencedId($productId);

        return $lineItem;
    }
}
