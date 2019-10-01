<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class CachedEntityAggregatorTest extends TestCase
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
        $dbalReader = $this->createMock(EntityAggregator::class);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $criteria = new Criteria([$id1, $id2]);

        $propertiesAggregation = new EntityAggregation('properties', 'product.properties.id', PropertyGroupOptionDefinition::class);
        $criteria->addAggregation($propertiesAggregation);

        $manufacturerAggregation = new EntityAggregation('manufacturer', 'product.manufacturer.id', ProductManufacturerDefinition::class);
        $criteria->addAggregation($manufacturerAggregation);

        $priceAggregation = new StatsAggregation('price', 'product.listingPrices');
        $criteria->addAggregation($priceAggregation);

        $context = Context::createDefaultContext();

        $configGroupEntity = new PropertyGroupOptionEntity();
        $configGroupEntity->setUniqueIdentifier('test');

        $manufacturerEntity = new ProductManufacturerEntity();
        $manufacturerEntity->setUniqueIdentifier('test');

        //read in EntityReader will be only called once
        $dbalReader->expects(static::once())
            ->method('aggregate')
            ->willReturn(
                new AggregationResultCollection(
                    [
                        new EntityResult('properties', new EntityCollection([$configGroupEntity])),
                        new EntityResult('manufacturer', new EntityCollection([$manufacturerEntity])),
                        new StatsResult('price', 0, 0, 0.0, 0.0),
                    ]
                )
            );

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityAggregator($this->cache, $dbalReader, $generator, true, 3600);

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->aggregate($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        //second call should hit the cache items and the dbal reader shouldn't be called
        $cachedEntities = $cachedReader->aggregate($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);
    }

    public function testMissingCacheHit(): void
    {
        $dbalReader = $this->createMock(EntityAggregator::class);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $criteria = new Criteria([$id1, $id2]);
        $criteria->addAggregation(
            new EntityAggregation('properties', 'product.properties.id', PropertyGroupOptionDefinition::class)
        );
        $criteria->addAggregation(
            new EntityAggregation('manufacturer', 'product.manufacturer.id', ProductManufacturerDefinition::class)
        );
        $criteria->addAggregation(
            new StatsAggregation('price', 'product.listingPrices')
        );

        $criteria2 = clone $criteria;
        $criteria2->addAggregation(new StatsAggregation('tax', 'product.tax'));

        $context = Context::createDefaultContext();

        //read in EntityReader will be only called once
        $dbalReader->expects(static::exactly(2))
            ->method('aggregate')
            ->willReturnCallback(
                function ($definition, $criteria, $context) {
                    $configGroupEntity = new PropertyGroupOptionEntity();
                    $configGroupEntity->setUniqueIdentifier('test');

                    $manufacturerEntity = new ProductManufacturerEntity();
                    $manufacturerEntity->setUniqueIdentifier('test');
                    if (!$criteria->getAggregation('tax')) {
                        return new AggregationResultCollection(
                            [
                                new EntityResult('properties', new EntityCollection([$configGroupEntity])),
                                new EntityResult('manufacturer', new EntityCollection([$manufacturerEntity])),
                                new StatsResult('price', 0, 0, 0.0, 0.0),
                            ]
                        );
                    }

                    return new AggregationResultCollection([new StatsResult('tax', 0, 0, 0.0, 0.0)]);
                }
            );

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityAggregator($this->cache, $dbalReader, $generator, true, 3600);

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->aggregate($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        //second call should hit the cache items and the dbal reader shouldn't be called
        $cachedEntities = $cachedReader->aggregate($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);

        //third call should hit the cache items and read one missing from dbal
        $cachedEntities = $cachedReader->aggregate($this->getContainer()->get(TaxDefinition::class), $criteria2, $context);

        static::assertNotEquals($databaseEntities, $cachedEntities);
    }

    public function testDisableCacheOption(): void
    {
        $dbalReader = $this->createMock(EntityAggregator::class);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $criteria = new Criteria([$id1, $id2]);

        $propertiesAggregation = new EntityAggregation('properties', 'product.properties.id', PropertyGroupOptionDefinition::class);
        $criteria->addAggregation($propertiesAggregation);

        $manufacturerAggregation = new EntityAggregation('manufacturer', 'product.manufacturer.id', ProductManufacturerDefinition::class);
        $criteria->addAggregation($manufacturerAggregation);

        $priceAggregation = new StatsAggregation('product.listingPrices', 'price');
        $criteria->addAggregation($priceAggregation);

        $context = Context::createDefaultContext();

        $configGroupEntity = new PropertyGroupOptionEntity();
        $configGroupEntity->setUniqueIdentifier('test');

        $manufacturerEntity = new ProductManufacturerEntity();
        $manufacturerEntity->setUniqueIdentifier('test');

        //read in EntityReader will be only called once
        $dbalReader->expects(static::atLeast(2))
            ->method('aggregate')
            ->willReturn(
                new AggregationResultCollection(
                    [
                        new EntityResult('properties', new EntityCollection([$configGroupEntity])),
                        new EntityResult('manufacturer', new EntityCollection([$manufacturerEntity])),
                    ]
                )
            );

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityAggregator($this->cache, $dbalReader, $generator, false, 3600);

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->aggregate($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        //cache is disabled. second call shouldn't hit the cache and the dbal reader should be called
        $cachedEntities = $cachedReader->aggregate($this->getContainer()->get(TaxDefinition::class), $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);
    }
}
