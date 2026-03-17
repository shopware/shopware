<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\QuantityLimits;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\QuantityLimits\ProductQuantityLimitsRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductQuantityLimitsRoute::class)]
class ProductQuantityLimitsRouteTest extends TestCase
{
    /**
     * @var MockObject&SalesChannelRepository<SalesChannelProductCollection>
     */
    private MockObject&SalesChannelRepository $productRepository;

    private MockObject&AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator;

    private ProductQuantityLimitsRoute $route;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->maxPurchaseCalculator = $this->createMock(AbstractProductMaxPurchaseCalculator::class);

        $this->route = new ProductQuantityLimitsRoute(
            $this->productRepository,
            $this->maxPurchaseCalculator,
        );
    }

    public function testLoadReturnsQuantityLimits(): void
    {
        $context = Generator::generateSalesChannelContext();
        $productId = Uuid::randomHex();

        $product = (new PartialEntity())->assign([
            'id' => $productId,
            'minPurchase' => 2,
            'purchaseSteps' => 2,
        ]);

        $this->productRepository->method('search')->willReturn(
            new EntitySearchResult('product', 1, new EntityCollection([$product]), null, new Criteria(), $context->getContext())
        );

        $this->maxPurchaseCalculator->method('calculate')->with($product, $context)->willReturn(10);

        $result = $this->route->load($productId, new Request(), $context)->getResult();

        static::assertSame($productId, $result->getProductId());
        static::assertSame(2, $result->getMinPurchase());
        static::assertSame(2, $result->getPurchaseSteps());
        static::assertSame(10, $result->getMaxPurchase());
    }

    public function testLoadDefaults(): void
    {
        $context = Generator::generateSalesChannelContext();
        $productId = Uuid::randomHex();

        $product = (new PartialEntity())->assign([
            'id' => $productId,
        ]);

        $this->productRepository->method('search')->willReturn(
            new EntitySearchResult('product', 1, new EntityCollection([$product]), null, new Criteria(), $context->getContext())
        );

        $this->maxPurchaseCalculator->method('calculate')->willReturn(5);

        $result = $this->route->load($productId, new Request(), $context)->getResult();

        static::assertSame(1, $result->getMinPurchase());
        static::assertSame(1, $result->getPurchaseSteps());
    }

    public function testLoadThrowsWhenProductNotFound(): void
    {
        $context = Generator::generateSalesChannelContext();
        $productId = Uuid::randomHex();

        $this->productRepository->method('search')->willReturn(
            new EntitySearchResult('product', 0, new EntityCollection(), null, new Criteria(), $context->getContext())
        );

        $this->expectExceptionObject(ProductException::productNotFound($productId));

        $this->route->load($productId, new Request(), $context);
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ProductQuantityLimitsRoute::class));
        $this->route->getDecorated();
    }
}
