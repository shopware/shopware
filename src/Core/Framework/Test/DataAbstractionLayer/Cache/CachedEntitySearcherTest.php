<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Tax\TaxDefinition;

class CachedEntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider searchCases
     */
    public function testCacheHits(Criteria $criteria, array $expectedTags): void
    {
        $expectedTags = array_combine($expectedTags, $expectedTags);

        $dbalSearcher = $this->createMock(EntitySearcher::class);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext();

        $dbalSearcher->expects(static::once())
            ->method('search')
            ->willReturn(
                new IdSearchResult(
                    0,
                    [
                        $id1 => ['id' => $id1],
                        $id2 => ['id' => $id2],
                    ],
                    $criteria,
                    $context
                )
            );

        $cache = $this->getContainer()->get('shopware.cache');

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedSearcher = new CachedEntitySearcher($generator, $cache, $dbalSearcher, true, 3600);

        //first call should not match and the expects of the dbal searcher should called
        $databaseResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        //second call should hit the cache items and the dbal searcher shouldn't be called
        $cachedResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseResult, $cachedResult);

        $cacheItem = $cache->getItem(
            $generator->getSearchCacheKey(TaxDefinition::class, $criteria, $context)
        );

        static::assertInstanceOf(IdSearchResult::class, $cacheItem->get());

        $metaData = $cacheItem->getMetadata();
        static::assertArrayHasKey('tags', $metaData);
        static::assertEquals($expectedTags, $metaData['tags']);
    }

    /**
     * @dataProvider searchCases
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testDisableCacheOption(Criteria $criteria, array $expectedTags): void
    {
        $expectedTags = array_combine($expectedTags, $expectedTags);

        $dbalSearcher = $this->createMock(EntitySearcher::class);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext();

        $dbalSearcher->expects(static::atLeast(2))
            ->method('search')
            ->willReturn(
                new IdSearchResult(
                    0,
                    [
                        $id1 => ['id' => $id1],
                        $id2 => ['id' => $id2],
                    ],
                    $criteria,
                    $context
                )
            );

        $cache = $this->getContainer()->get('shopware.cache');

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedSearcher = new CachedEntitySearcher($generator, $cache, $dbalSearcher, false, 3600);

        //first call should not match and the expects of the dbal searcher should called
        $databaseResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        //cache is disabled. second call shouldn't hit the cache and the dbal reader should be called
        $cachedResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseResult, $cachedResult);

        $cacheItem = $cache->getItem(
            $generator->getSearchCacheKey(TaxDefinition::class, $criteria, $context)
        );

        static::assertInstanceOf(IdSearchResult::class, $cacheItem->get());

        $metaData = $cacheItem->getMetadata();
        static::assertArrayHasKey('tags', $metaData);
        static::assertEquals($expectedTags, $metaData['tags']);
    }

    public function searchCases(): array
    {
        return [
            //test case that filters considered
            [
                (new Criteria())->addFilter(new EqualsFilter('tax.name', 'Test')),

                ['tax.name', 'tax.id'], //expected tags
            ],
            //test case that multiple filters considered
            [
                (new Criteria())->addFilter(new EqualsFilter('tax.name', 'Test'))
                                ->addFilter(new EqualsFilter('tax.id', 'Test'))
                                ->addFilter(new EqualsFilter('tax.products.id', 'Test')),

                ['tax.name', 'product.id', 'tax.id', 'product.tax_id'], //expected tags
            ],
            //test case that sortings considered
            [
                (new Criteria())->addSorting(new FieldSorting('tax.name')),

                ['tax.name', 'tax.id'], //expected tags
            ],

            //test case that multiple sortings considered
            [
                (new Criteria())->addSorting(new FieldSorting('tax.name'))
                                ->addSorting(new FieldSorting('tax.products.cover.id')),

                ['tax.name', 'tax.id', 'product.tax_id', 'product.product_media_id', 'product_media.id'], //expected tags
            ],

            //test case that multiple post-filters considered
            [
                (new Criteria())->addPostFilter(new EqualsFilter('tax.name', 'Test'))
                                ->addPostFilter(new EqualsFilter('tax.taxRate', 'Test')),

                ['tax.id', 'tax.name', 'tax.tax_rate'], //expected tags
            ],
            //test case that pagination is considered
            [
                new PaginationCriteria(0, 10),
                ['tax.id'],
            ],
        ];
    }
}
