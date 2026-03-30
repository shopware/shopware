<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\ProductDeliveryCostRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\Cart\ProductGatewayInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ProductDeliveryCostRoute::class)]
class ProductDeliveryCostRouteTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $route = new ProductDeliveryCostRoute(
            $this->createMock(ProductGatewayInterface::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(Processor::class),
        );

        $this->expectException(DecorationPatternException::class);

        $route->getDecorated();
    }

    public function testDeliveryCostsByProductWithShippingMethodCriteriaReturnsRequestedShippingMethods(): void
    {
        $shippingMethod = $this->createShippingMethod('shipping-1');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod);
        $product = $this->createProduct('product-1');
        $shippingMethods = new ShippingMethodCollection([$shippingMethod]);

        $route = new ProductDeliveryCostRoute(
            $this->createProductGatewayMock($product, $context),
            $this->createShippingMethodRepositoryMock($shippingMethods, $context, [$shippingMethod->getId()]),
            $this->createProcessorMock(1),
        );

        $response = $route->deliveryCostsByProduct($product->getId(), new Criteria([$shippingMethod->getId()]), $context);

        static::assertCount(1, $response->getDeliveryCosts());
        static::assertSame(10.0, $response->getShippingCost($shippingMethod->getId())?->getTotalPrice());
        static::assertSame($shippingMethod, $response->getShippingMethod($shippingMethod->getId()));
    }

    public function testDeliveryCostsByProductWithoutCriteriaIdsReturnsAllShippingMethods(): void
    {
        $shippingMethod1 = $this->createShippingMethod('shipping-1');
        $shippingMethod2 = $this->createShippingMethod('shipping-2');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod1);
        $product = $this->createProduct('product-1');
        $shippingMethods = new ShippingMethodCollection([$shippingMethod1, $shippingMethod2]);

        $route = new ProductDeliveryCostRoute(
            $this->createProductGatewayMock($product, $context),
            $this->createShippingMethodRepositoryMock($shippingMethods, $context),
            $this->createProcessorMock(2),
        );

        $response = $route->deliveryCostsByProduct($product->getId(), new Criteria(), $context);

        static::assertCount(2, $response->getDeliveryCosts());
        static::assertNotNull($response->getShippingCost($shippingMethod1->getId()));
        static::assertNotNull($response->getShippingCost($shippingMethod2->getId()));
    }

    private function createShippingMethod(string $id): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($id);
        $shippingMethod->setName('Shipping ' . $id);
        $shippingMethod->setActive(true);

        return $shippingMethod;
    }

    private function createProduct(string $id): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId($id);

        return $product;
    }

    private function createProductGatewayMock(ProductEntity $product, SalesChannelContext $context): ProductGatewayInterface
    {
        $productGateway = $this->createMock(ProductGatewayInterface::class);
        $productGateway
            ->expects($this->once())
            ->method('get')
            ->with([$product->getId()], $context)
            ->willReturn(new ProductCollection([$product]));

        return $productGateway;
    }

    /**
     * @param list<string>|null $expectedIds
     *
     * @return EntityRepository<ShippingMethodCollection>
     */
    private function createShippingMethodRepositoryMock(
        ShippingMethodCollection $shippingMethods,
        SalesChannelContext $context,
        ?array $expectedIds = null,
    ): EntityRepository {
        $searchResult = new EntitySearchResult(
            'shipping_method',
            $shippingMethods->count(),
            $shippingMethods,
            null,
            new Criteria($expectedIds),
            $context->getContext()
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        return $repository;
    }

    private function createProcessorMock(int $expectedCalls): Processor
    {
        $processor = $this->createMock(Processor::class);
        $processor
            ->expects($this->exactly($expectedCalls))
            ->method('process')
            ->with(
                static::isInstanceOf(Cart::class),
                static::isInstanceOf(SalesChannelContext::class),
                static::isInstanceOf(CartBehavior::class)
            )
            ->willReturn(Generator::createCartWithDelivery());

        return $processor;
    }
}
