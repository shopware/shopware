<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\PurchaseLimit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\PurchaseLimit\ProductPurchaseLimitRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPurchaseLimitRoute::class)]
class ProductPurchaseLimitRouteTest extends TestCase
{
    /**
     * @var Stub&SalesChannelRepository<SalesChannelProductCollection>
     */
    private Stub&SalesChannelRepository $productRepository;

    private Stub&AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator;

    private ProductPurchaseLimitRoute $route;

    protected function setUp(): void
    {
        $this->productRepository = static::createStub(SalesChannelRepository::class);
        $this->maxPurchaseCalculator = static::createStub(AbstractProductMaxPurchaseCalculator::class);

        $this->route = $this->createRoute();
    }

    public function testLoadReturnsMultipleProductLimits(): void
    {
        $context = Generator::generateSalesChannelContext();
        $productIdA = Uuid::randomHex();
        $productIdB = Uuid::randomHex();

        $productA = (new PartialEntity())->assign([
            'id' => $productIdA,
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'stock' => 100,
        ]);

        $productB = (new PartialEntity())->assign([
            'id' => $productIdB,
            'minPurchase' => 5,
            'purchaseSteps' => 5,
            'stock' => 3,
        ]);

        $this->productRepository->method('search')->willReturn(
            new EntitySearchResult('product', 2, new EntityCollection([$productA, $productB]), null, new Criteria(), $context->getContext())
        );

        $this->maxPurchaseCalculator->method('calculate')->willReturnMap([
            [$productA, $context, 20],
            [$productB, $context, 50],
        ]);

        $request = new Request(['ids' => [$productIdA, $productIdB]]);
        $results = $this->route->readProductsPurchaseLimit($request, $context)->getResult();

        static::assertCount(2, $results);

        $items = array_values($results->getElements());

        static::assertSame($productIdA, $items[0]->getProductId());
        static::assertSame(1, $items[0]->getMinPurchase());
        static::assertSame(1, $items[0]->getPurchaseSteps());
        static::assertSame(20, $items[0]->getMaxPurchase());
        static::assertSame(100, $items[0]->getStock());

        static::assertSame($productIdB, $items[1]->getProductId());
        static::assertSame(5, $items[1]->getMinPurchase());
        static::assertSame(5, $items[1]->getPurchaseSteps());
        static::assertSame(50, $items[1]->getMaxPurchase());
        static::assertSame(3, $items[1]->getStock());
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

        $request = new Request(['ids' => [$productId]]);
        $result = $this->route->readProductsPurchaseLimit($request, $context)->getResult()->first();

        static::assertNotNull($result);
        static::assertSame(1, $result->getMinPurchase());
        static::assertSame(1, $result->getPurchaseSteps());
        static::assertNull($result->getStock());
    }

    public function testLoadReturnsEmptyCollectionForUnknownIds(): void
    {
        $context = Generator::generateSalesChannelContext();
        $productId = Uuid::randomHex();

        $this->productRepository->method('search')->willReturn(
            new EntitySearchResult('product', 0, new EntityCollection(), null, new Criteria(), $context->getContext())
        );

        $request = new Request(['ids' => [$productId]]);
        $results = $this->route->readProductsPurchaseLimit($request, $context)->getResult();

        static::assertCount(0, $results);
    }

    public function testLoadThrowsForEmptyIds(): void
    {
        $context = Generator::generateSalesChannelContext();

        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->never())->method('search');

        $this->expectException(ProductException::class);

        $this->createRoute($productRepository)->readProductsPurchaseLimit(new Request(), $context);
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ProductPurchaseLimitRoute::class));
        $this->route->getDecorated();
    }

    /**
     * @param (SalesChannelRepository<SalesChannelProductCollection>&MockObject)|null $productRepository
     */
    private function createRoute(?SalesChannelRepository $productRepository = null): ProductPurchaseLimitRoute
    {
        return new ProductPurchaseLimitRoute(
            $productRepository ?? $this->productRepository,
            $this->maxPurchaseCalculator,
        );
    }
}
