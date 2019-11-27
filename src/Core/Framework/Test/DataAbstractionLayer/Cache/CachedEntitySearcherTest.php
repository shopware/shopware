<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\Tax\TaxDefinition;

class CachedEntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @dataProvider searchCases
     */
    public function testCacheHits(Criteria $criteria, array $expectedTags): void
    {
        $expectedTags = array_combine($expectedTags, $expectedTags);

        $dbalSearcher = $this->createMock(EntitySearcher::class);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $dbalSearcher->expects(static::once())
            ->method('search')
            ->willReturn(
                new IdSearchResult(
                    0,
                    [
                        $id1 => ['primaryKey' => $id1, 'data' => []],
                        $id2 => ['primaryKey' => $id2, 'data' => []],
                    ],
                    $criteria,
                    $context
                )
            );

        $cache = $this->getContainer()->get('cache.object');

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedSearcher = new CachedEntitySearcher($generator, $cache, $dbalSearcher, true, 3600);

        //first call should not match and the expects of the dbal searcher should called
        $databaseResult = $cachedSearcher->search($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        //second call should hit the cache items and the dbal searcher shouldn't be called
        $cachedResult = $cachedSearcher->search($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        static::assertEquals($databaseResult, $cachedResult);

        $cacheItem = $cache->getItem(
            $generator->getSearchCacheKey($this->getContainer()->get(TaxDefinition::class), $criteria, $context)
        );

        static::assertInstanceOf(IdSearchResult::class, $cacheItem->get());

        $metaData = $cacheItem->getMetadata();
        static::assertArrayHasKey('tags', $metaData);
        static::assertSame($expectedTags, $metaData['tags']);
    }

    /**
     * @dataProvider searchCases
     */
    public function testDisableCacheOption(Criteria $criteria): void
    {
        $context = Context::createDefaultContext();
        $context->disableCache(function (Context $context) use ($criteria): void {
            $dbalSearcher = $this->createMock(EntitySearcher::class);

            $id1 = Uuid::randomHex();
            $id2 = Uuid::randomHex();

            $dbalSearcher->expects(static::atLeast(2))
                ->method('search')
                ->willReturn(
                    new IdSearchResult(
                        0,
                        [
                            $id1 => ['primaryKey' => $id1, 'data' => []],
                            $id2 => ['primaryKey' => $id2, 'data' => []],
                        ],
                        $criteria,
                        $context
                    )
                );

            $cache = $this->getContainer()->get('cache.object');

            $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

            $cachedSearcher = new CachedEntitySearcher($generator, $cache, $dbalSearcher, false, 3600);

            //first call should not match and the expects of the dbal searcher should called
            $databaseResult = $cachedSearcher->search($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

            //cache is disabled. second call shouldn't hit the cache and the dbal reader should be called
            $cachedResult = $cachedSearcher->search($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

            static::assertSame($databaseResult, $cachedResult);

            $cacheItem = $cache->getItem(
                $generator->getSearchCacheKey($this->getContainer()->get(TaxDefinition::class), $criteria, $context)
            );

            static::assertNull($cacheItem->get());
        });
    }

    public function searchCases(): array
    {
        return [
            //test case that filters considered
            [
                (new Criteria())->addFilter(new EqualsFilter('tax.name', 'Test')),

                ['tax.id', 'tax.name'], //expected tags
            ],
            //test case that multiple filters considered
            [
                (new Criteria())->addFilter(new EqualsFilter('tax.name', 'Test'))
                                ->addFilter(new EqualsFilter('tax.id', 'Test'))
                                ->addFilter(new EqualsFilter('tax.products.id', 'Test')),

                ['tax.id', 'tax.name', 'product.tax_id', 'product.id'], //expected tags
            ],
            //test case that sortings are considered
            [
                (new Criteria())->addSorting(new FieldSorting('tax.name')),

                ['tax.id', 'tax.name'], //expected tags
            ],

            //test case that multiple sortings are considered
            [
                (new Criteria())->addSorting(new FieldSorting('tax.name'))
                                ->addSorting(new FieldSorting('tax.products.cover.id')),

                ['tax.id', 'tax.name', 'product.tax_id', 'product.product_media_id', 'product_media.id'], //expected tags
            ],

            //test case that multiple post-filters considered
            [
                (new Criteria())->addPostFilter(new EqualsFilter('tax.name', 'Test'))
                                ->addPostFilter(new EqualsFilter('tax.taxRate', 'Test')),

                ['tax.id', 'tax.name', 'tax.tax_rate'], //expected tags
            ],
            //test case that pagination is considered
            [
                (new Criteria())->setLimit(0)->setOffset(10),
                ['tax.id'],
            ],
        ];
    }

    public function testSalesChannelProductRead(): void
    {
        /** @var EntityRepositoryInterface $productRepo */
        $productRepo = $this->getContainer()->get('product.repository');

        /** @var EntityRepositoryInterface $saleschannelProductRepo */
        $salesChannelProductRepo = $this->getContainer()->get('sales_channel.product.repository');

        $id = Uuid::randomHex();
        $product = [
            'id' => $id,
            'name' => 'foo',
            'productNumber' => 'FOO1',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'Test Product',
            ],
            'active' => true,
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'tax foo',
                'taxRate' => 15,
            ],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.0, 'net' => 10.0, 'linked' => false]],
            'stock' => 10,
            'visibilities' => [
                [
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextService::class)
            ->get(Defaults::SALES_CHANNEL, Uuid::randomHex());
        $context = $salesChannelContext->getContext();

        $productRepo->create([$product], $context);
        $this->addTaxDataToSalesChannel($salesChannelContext, $product['tax']);

        $criteria = new Criteria();
        $first = $productRepo->search($criteria, $context)->first();
        static::assertInstanceOf(ProductEntity::class, $first);

        $first = $salesChannelProductRepo->search($criteria, $salesChannelContext)->first();
        static::assertInstanceOf(SalesChannelProductEntity::class, $first);
    }
}
