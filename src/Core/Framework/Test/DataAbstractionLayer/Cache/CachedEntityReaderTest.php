<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class CachedEntityReaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TagAwareAdapter
     */
    protected $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->getContainer()->get('cache.object');
    }

    public function testCacheHit(): void
    {
        $dbalReader = $this->createMock(EntityReader::class);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        //read in EntityReader will be only called once
        $dbalReader->expects(static::once())
            ->method('read')
            ->willReturn(
                new TaxCollection([
                    (new TaxEntity())->assign([
                        'id' => $id1,
                        '_uniqueIdentifier' => $id1,
                        'taxRate' => 15,
                        'name' => 'test',
                        'products' => new ProductCollection([
                            (new ProductEntity())->assign([
                                'id' => $id1,
                                '_uniqueIdentifier' => $id1,
                                'tax' => (new TaxEntity())->assign([
                                    'id' => $id1,
                                    '_uniqueIdentifier' => $id1,
                                ]),
                            ]),
                        ]),
                    ]),
                    (new TaxEntity())->assign([
                        'id' => $id2,
                        '_uniqueIdentifier' => $id2,
                        'taxRate' => 12,
                        'name' => 'test2',
                    ]),
                ])
            );

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityReader($this->cache, $dbalReader, $generator, true, 3600);

        $criteria = new Criteria([$id1, $id2]);

        $context = Context::createDefaultContext();

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->read($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        //second call should hit the cache items and the dbal reader shouldn't be called
        $cachedEntities = $cachedReader->read($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);
    }

    /**
     * Read two ids without filter, then read same ids with one of them filtered.
     */
    public function testCacheHitWithFilters(): void
    {
        $dbalReader = $this->createMock(EntityReader::class);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $criteria = new Criteria([$id1, $id2]);
        $criteria->addFilter(new RangeFilter('taxRate', ['gte' => 10.00]));

        $criteria2 = new Criteria([$id1, $id2]);
        $criteria2->addFilter(new RangeFilter('taxRate', ['gte' => 13.00]));
        $context = Context::createDefaultContext();

        //read in EntityReader will be only called twice
        $dbalReader->expects(static::exactly(2))
            ->method('read')
            ->willReturnMap(
                [
                    [
                        $this->getContainer()->get(TaxDefinition::class),
                        $criteria,
                        $context,
                        new TaxCollection([
                            (new TaxEntity())->assign([
                                'id' => $id1,
                                '_uniqueIdentifier' => $id1,
                                'taxRate' => 15,
                                'name' => 'test',
                                'products' => new ProductCollection([
                                    (new ProductEntity())->assign([
                                        'id' => $id1,
                                        '_uniqueIdentifier' => $id1,
                                        'tax' => (new TaxEntity())->assign([
                                            'id' => $id1,
                                            '_uniqueIdentifier' => $id1,
                                        ]),
                                    ]),
                                ]),
                            ]),
                            (new TaxEntity())->assign([
                                'id' => $id2,
                                '_uniqueIdentifier' => $id2,
                                'taxRate' => 12,
                                'name' => 'test2',
                            ]),
                        ]),
                    ],
                    [
                        $this->getContainer()->get(TaxDefinition::class),
                        $criteria2,
                        $context,
                        new TaxCollection([
                            (new TaxEntity())->assign([
                                'id' => $id1,
                                '_uniqueIdentifier' => $id1,
                                'taxRate' => 15,
                                'name' => 'test',
                                'products' => new ProductCollection([
                                    (new ProductEntity())->assign([
                                        'id' => $id1,
                                        '_uniqueIdentifier' => $id1,
                                        'tax' => (new TaxEntity())->assign([
                                            'id' => $id1,
                                            '_uniqueIdentifier' => $id1,
                                        ]),
                                    ]),
                                ]),
                            ]),
                        ]),
                    ],
                ]
            );

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityReader($this->cache, $dbalReader, $generator, true, 3600);

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->read($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        //second call should hit the cache items and the dbal reader shouldn't be called
        $cachedEntities = $cachedReader->read($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);

        //call with different Filter should call the dbal reader again
        $differentEntities = $cachedReader->read($this->getContainer()->get(TaxDefinition::class), $criteria2, $context);

        //second call with different Filter should hit the cache items and the dbal reader shouldn't be called
        $differentCachedEntities = $cachedReader->read($this->getContainer()->get(TaxDefinition::class), $criteria2, $context);

        static::assertEquals($differentEntities, $differentCachedEntities);

        static::assertNotEquals($databaseEntities, $differentEntities);
    }

    public function testDisableCacheOption(): void
    {
        $dbalReader = $this->createMock(EntityReader::class);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        //read in EntityReader will be only called once
        $dbalReader->expects(static::atLeast(2))
            ->method('read')
            ->willReturn(
                new TaxCollection([
                    (new TaxEntity())->assign([
                        'id' => $id1,
                        '_uniqueIdentifier' => $id1,
                        'taxRate' => 15,
                        'name' => 'test',
                        'products' => new ProductCollection([
                            (new ProductEntity())->assign([
                                'id' => $id1,
                                '_uniqueIdentifier' => $id1,
                                'tax' => (new TaxEntity())->assign([
                                    'id' => $id1,
                                    '_uniqueIdentifier' => $id1,
                                ]),
                            ]),
                        ]),
                    ]),
                    (new TaxEntity())->assign([
                        'id' => $id2,
                        '_uniqueIdentifier' => $id2,
                        'taxRate' => 12,
                        'name' => 'test2',
                    ]),
                ])
            );

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityReader($this->cache, $dbalReader, $generator, false, 3600);

        $criteria = new Criteria([$id1, $id2]);

        $context = Context::createDefaultContext();

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->read($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        //cache is disabled. second call shouldn't hit the cache and the dbal reader should be called
        $cachedEntities = $cachedReader->read($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);
    }
}
