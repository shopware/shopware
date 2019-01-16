<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Tax\TaxDefinition;

class CachedEntitySearcherTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCacheHit()
    {
        $dbalSearcher = $this->createMock(EntitySearcher::class);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $criteria = new Criteria();
        $context = Context::createDefaultContext();
        $dbalSearcher->expects(static::exactly(2))
            ->method('search')
            ->will(
                $this->returnValue(
                    new IdSearchResult(
                        0,
                        [
                            $id1 => ['id' => $id1],
                            $id2 => ['id' => $id2],
                        ],
                        $criteria,
                        $context
                    )
                )
            );

        $cache = $this->getContainer()->get('shopware.cache');
        $cache->clear();

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedSearcher = new CachedEntitySearcher($generator, $cache, $dbalSearcher);

        $context = Context::createDefaultContext();

        //first call should not match and the expects of the dbal searcher should called
        $databaseResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        //second call should hit the cache items and the dbal searcher shouldn't be called
        $cachedResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseResult, $cachedResult);

        $criteria->addSorting(new FieldSorting('tax.name'));

        //after changing the criteria, the cache shouldn't hit
        $cachedSearcher->search(TaxDefinition::class, $criteria, $context);
    }

    public function testCacheHitWithFilters()
    {
        $dbalSearcher = $this->createMock(EntitySearcher::class);

        $context = Context::createDefaultContext();

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $criteria = new Criteria();
        $criteria2 = new Criteria();
        $criteria2->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('id',$id2)]));
        $context = Context::createDefaultContext();
        $dbalSearcher->expects(static::exactly(2))
            ->method('search')
            ->will(
                $this->returnValueMap(
                    [
                        [
                            TaxDefinition::class, $criteria, $context,
                            new IdSearchResult(
                                2,
                                [
                                    $id1 => ['id' => $id1],
                                    $id2 => ['id' => $id2],
                                ],
                                $criteria,
                                $context
                            )
                        ],
                        [
                            TaxDefinition::class, $criteria2, $context,
                            new IdSearchResult(
                                1,
                                [
                                    $id1 => [
                                        'id' => $id1
                                    ]
                                ],
                                $criteria,
                                $context
                            )
                        ]
                    ]
                )
            );

        $cache = $this->getContainer()->get('shopware.cache');
        $cache->clear();

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedSearcher = new CachedEntitySearcher($generator, $cache, $dbalSearcher);

        //first call should not match and the expects of the dbal searcher should called
        $databaseResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        //second call should hit the cache items and the dbal searcher shouldn't be called
        $cachedResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseResult, $cachedResult);

        //after changing the criteria, the cache shouldn't hit
        $databaseFilteredResult = $cachedSearcher->search(TaxDefinition::class, $criteria2, $context);

        //after changing the criteria, the cache shouldn't hit
        $cachedFilteredResult = $cachedSearcher->search(TaxDefinition::class, $criteria2, $context);

        static::assertCount(1, $databaseFilteredResult->getData());
        static::assertEquals($databaseFilteredResult, $cachedFilteredResult);
    }

    public function testCacheHitWithAssociations()
    {
        $dbalSearcher = $this->createMock(EntitySearcher::class);

        $context = Context::createDefaultContext();

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $criteria = new Criteria();
        $criteria2 = new Criteria();
        $criteria2->addAssociation('any.thing');
        $context = Context::createDefaultContext();
        $dbalSearcher->expects(static::exactly(2))
            ->method('search')
            ->will(
                $this->returnValueMap(
                    [
                        [
                            TaxDefinition::class, $criteria, $context,
                            new IdSearchResult(
                                2,
                                [
                                    $id1 => ['id' => $id1],
                                    $id2 => ['id' => $id2],
                                ],
                                $criteria,
                                $context
                            )
                        ],
                        [
                            TaxDefinition::class, $criteria2, $context,
                            new IdSearchResult(
                                2,
                                [
                                    $id1 => [
                                        'id' => $id1,
                                        'any' => 'thing'
                                    ],
                                    $id2 => [
                                        'id' => $id2,
                                        'any' => 'thing'
                                    ],
                                ],
                                $criteria,
                                $context
                            )
                        ]
                    ]
                )
            );

        $cache = $this->getContainer()->get('shopware.cache');
        $cache->clear();

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedSearcher = new CachedEntitySearcher($generator, $cache, $dbalSearcher);

        //first call should not match and the expects of the dbal searcher should called
        $databaseResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        //second call should hit the cache items and the dbal searcher shouldn't be called
        $cachedResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseResult, $cachedResult);

        //after changing the criteria, the cache shouldn't hit
        $databaseAssocedResult = $cachedSearcher->search(TaxDefinition::class, $criteria2, $context);

        //after changing the criteria, the cache shouldn't hit
        $cachedAssocedResult = $cachedSearcher->search(TaxDefinition::class, $criteria2, $context);

        static::assertEquals($databaseAssocedResult->getDataFieldOfId($id1, 'any'), 'thing');
        static::assertEquals($databaseAssocedResult, $cachedAssocedResult);
    }
}
