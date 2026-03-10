<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\BestsellerReportTool;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BestsellerReportTool::class)]
class BestsellerReportToolTest extends TestCase
{
    public function testReturnsTopProductsSortedByQuantity(): void
    {
        $productA = Uuid::randomHex();
        $productB = Uuid::randomHex();

        $tool = $this->createTool(
            bestsellerBuckets: [
                new Bucket($productA, 5, new SumResult('totalQuantity', 8.0)),
                new Bucket($productB, 2, new SumResult('totalQuantity', 2.0)),
            ],
            revenueBuckets: [
                new Bucket($productA, 5, new SumResult('totalRevenue', 400.0)),
                new Bucket($productB, 2, new SumResult('totalRevenue', 100.0)),
            ],
            products: [
                $this->buildProduct($productA, 'SW-001', 'Red Shoes'),
                $this->buildProduct($productB, 'SW-002', 'Blue Hat'),
            ],
        );

        $output = ($tool)('2025-01-01', '2025-01-31');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame(['from' => '2025-01-01', 'to' => '2025-01-31'], $data['data']['period']);
        static::assertCount(2, $data['data']['bestsellers']);

        $first = $data['data']['bestsellers'][0];
        static::assertSame($productA, $first['productId']);
        static::assertSame('SW-001', $first['productNumber']);
        static::assertSame('Red Shoes', $first['name']);
        static::assertSame(8, $first['totalQuantity']);
        static::assertSame(5, $first['orderCount']);
        static::assertEqualsWithDelta(400.0, $first['totalRevenue'], 0.01);

        $second = $data['data']['bestsellers'][1];
        static::assertSame($productB, $second['productId']);
        static::assertSame(2, $second['totalQuantity']);
    }

    public function testRespectsLimitParameter(): void
    {
        $productA = Uuid::randomHex();
        $productB = Uuid::randomHex();

        $tool = $this->createTool(
            bestsellerBuckets: [
                new Bucket($productA, 5, new SumResult('totalQuantity', 10.0)),
                new Bucket($productB, 3, new SumResult('totalQuantity', 3.0)),
            ],
            revenueBuckets: [
                new Bucket($productA, 5, new SumResult('totalRevenue', 500.0)),
                new Bucket($productB, 3, new SumResult('totalRevenue', 150.0)),
            ],
            products: [
                $this->buildProduct($productA, 'SW-001', 'Top Product'),
            ],
        );

        $output = ($tool)('2025-01-01', '2025-01-31', limit: 1);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertCount(1, $data['data']['bestsellers']);
        static::assertSame($productA, $data['data']['bestsellers'][0]['productId']);
    }

    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new BestsellerReportTool($registry, $contextProvider);
        $output = ($tool)('2025-01-01', '2025-01-31');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('order:read', $data['error']);
    }

    public function testLimitOutOfRangeReturnsError(): void
    {
        $tool = $this->createTool(bestsellerBuckets: [], revenueBuckets: [], products: []);

        $output = ($tool)('2025-01-01', '2025-01-31', limit: 0);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('between 1 and 100', $data['error']);
    }

    public function testNonSumResultAggregationFallsBackToZero(): void
    {
        $productId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $aggregations = new AggregationResultCollection([
            new TermsResult('bestsellers', [
                new Bucket($productId, 3, new TermsResult('totalQuantity', [])),
            ]),
            new TermsResult('revenue', [
                new Bucket($productId, 3, new TermsResult('totalRevenue', [])),
            ]),
        ]);

        $lineItemResult = new EntitySearchResult('order_line_item', 0, new EntityCollection(), $aggregations, new Criteria(), $context);

        $lineItemRepo = static::createStub(EntityRepository::class);
        $lineItemRepo->method('search')->willReturn($lineItemResult);

        $product = $this->buildProduct($productId, 'SW-001', 'Test');
        $productResult = new EntitySearchResult('product', 1, new ProductCollection([$product]), null, new Criteria(), $context);

        $productRepo = static::createStub(EntityRepository::class);
        $productRepo->method('search')->willReturn($productResult);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturnMap([
            ['order_line_item', $lineItemRepo],
            ['product', $productRepo],
        ]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new BestsellerReportTool($registry, $contextProvider);
        $output = ($tool)('2025-01-01', '2025-01-31');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertCount(1, $data['data']['bestsellers']);
        static::assertSame(0, $data['data']['bestsellers'][0]['totalQuantity']);
        static::assertSame(0, $data['data']['bestsellers'][0]['totalRevenue']);
    }

    public function testSalesChannelIdFilterIsApplied(): void
    {
        $productId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $aggregations = new AggregationResultCollection([
            new TermsResult('bestsellers', [
                new Bucket($productId, 1, new SumResult('totalQuantity', 5.0)),
            ]),
            new TermsResult('revenue', [
                new Bucket($productId, 1, new SumResult('totalRevenue', 100.0)),
            ]),
        ]);

        $lineItemResult = new EntitySearchResult('order_line_item', 0, new EntityCollection(), $aggregations, new Criteria(), $context);

        $lineItemRepo = $this->createMock(EntityRepository::class);
        $lineItemRepo->expects($this->once())
            ->method('search')
            ->with(static::callback(function (Criteria $criteria) use ($salesChannelId): bool {
                foreach ($criteria->getFilters() as $filter) {
                    if ($filter instanceof EqualsFilter
                        && $filter->getField() === 'order.salesChannelId'
                        && $filter->getValue() === $salesChannelId) {
                        return true;
                    }
                }

                return false;
            }))
            ->willReturn($lineItemResult);

        $product = $this->buildProduct($productId, 'SW-001', 'Test');
        $productResult = new EntitySearchResult('product', 1, new ProductCollection([$product]), null, new Criteria(), $context);

        $productRepo = static::createStub(EntityRepository::class);
        $productRepo->method('search')->willReturn($productResult);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturnMap([
            ['order_line_item', $lineItemRepo],
            ['product', $productRepo],
        ]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new BestsellerReportTool($registry, $contextProvider);
        $output = ($tool)('2025-01-01', '2025-01-31', 10, $salesChannelId);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertCount(1, $data['data']['bestsellers']);
    }

    public function testEmptyResultReturnsEmptyBestsellers(): void
    {
        $tool = $this->createTool(bestsellerBuckets: [], revenueBuckets: [], products: []);

        $output = ($tool)('2025-01-01', '2025-01-31');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame([], $data['data']['bestsellers']);
        static::assertSame(['from' => '2025-01-01', 'to' => '2025-01-31'], $data['data']['period']);
    }

    /**
     * @param list<Bucket> $bestsellerBuckets
     * @param list<Bucket> $revenueBuckets
     * @param list<ProductEntity> $products
     */
    private function createTool(array $bestsellerBuckets, array $revenueBuckets, array $products): BestsellerReportTool
    {
        $context = Context::createDefaultContext();

        $aggregations = new AggregationResultCollection([
            new TermsResult('bestsellers', $bestsellerBuckets),
            new TermsResult('revenue', $revenueBuckets),
        ]);

        $lineItemResult = new EntitySearchResult(
            'order_line_item',
            0,
            new EntityCollection(),
            $aggregations,
            new Criteria(),
            $context,
        );

        $lineItemRepository = $this->createMock(EntityRepository::class);
        $lineItemRepository->method('search')->willReturn($lineItemResult);

        $productCollection = new ProductCollection($products);
        $productResult = new EntitySearchResult(
            'product',
            $productCollection->count(),
            $productCollection,
            null,
            new Criteria(),
            $context,
        );

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('search')->willReturn($productResult);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturnMap([
            ['order_line_item', $lineItemRepository],
            ['product', $productRepository],
        ]);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        return new BestsellerReportTool($registry, $contextProvider);
    }

    private function buildProduct(string $id, string $productNumber, string $name): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId($id);
        $product->setProductNumber($productNumber);
        $product->setName($name);
        $product->setUniqueIdentifier($id);

        return $product;
    }
}
