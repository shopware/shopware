<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregatorResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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
        $this->cache = $this->getContainer()->get('shopware.cache');
    }

    public function testCacheHit(): void
    {
        $dbalReader = $this->createMock(EntityAggregator::class);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $criteria = new Criteria([$id1, $id2]);

        $datasheetAggregation = new EntityAggregation('product.datasheet.id', ConfigurationGroupOptionDefinition::class, 'datasheet');
        $criteria->addAggregation($datasheetAggregation);

        $manufacturerAggregation = new EntityAggregation('product.manufacturer.id', ProductManufacturerDefinition::class, 'manufacturer');
        $criteria->addAggregation($manufacturerAggregation);

        $priceAggregation = new StatsAggregation('product.listingPrices', 'price', false);
        $criteria->addAggregation($priceAggregation);

        $context = Context::createDefaultContext();

        $configGroupEntity = new ConfigurationGroupOptionEntity();
        $configGroupEntity->setUniqueIdentifier('test');

        $manufacturerEntity = new ProductManufacturerEntity();
        $manufacturerEntity->setUniqueIdentifier('test');

        //read in EntityReader will be only called once
        $dbalReader->expects(static::once())
            ->method('aggregate')
            ->willReturn(
                new AggregatorResult(
                    new AggregationResultCollection(
                        [
                            new AggregationResult(
                                $datasheetAggregation,
                                [
                                    [
                                        'key' => null,
                                        'values' => new EntityCollection([$configGroupEntity]),
                                    ],
                                ]
                            ),
                            new AggregationResult(
                                $manufacturerAggregation,
                                [
                                    [
                                        'key' => null,
                                        'values' => new EntityCollection([$manufacturerEntity]),
                                    ],
                                ]
                            ),
                            new AggregationResult($priceAggregation, []),
                        ]
                    ),
                    $context,
                    $criteria
                )
            );

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityAggregator($this->cache, $dbalReader, $generator, true, 3600);

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->aggregate(TaxDefinition::class, $criteria, $context);

        //second call should hit the cache items and the dbal reader shouldn't be called
        $cachedEntities = $cachedReader->aggregate(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);
    }

    public function testMissingCacheHit(): void
    {
        $dbalReader = $this->createMock(EntityAggregator::class);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $criteria = new Criteria([$id1, $id2]);
        $criteria->addAggregation(
            new EntityAggregation('product.datasheet.id', ConfigurationGroupOptionDefinition::class, 'datasheet')
        );
        $criteria->addAggregation(
            new EntityAggregation('product.manufacturer.id', ProductManufacturerDefinition::class, 'manufacturer')
        );
        $criteria->addAggregation(
            new StatsAggregation('product.listingPrices', 'price', false)
        );

        $criteria2 = clone $criteria;
        $criteria2->addAggregation(
            new StatsAggregation('product.tax', 'tax', false)
        );

        $context = Context::createDefaultContext();

        //read in EntityReader will be only called once
        $dbalReader->expects(static::exactly(2))
            ->method('aggregate')
            ->willReturnCallback(
                function ($definition, $criteria, $context) {
                    $configGroupEntity = new ConfigurationGroupOptionEntity();
                    $configGroupEntity->setUniqueIdentifier('test');

                    $manufacturerEntity = new ProductManufacturerEntity();
                    $manufacturerEntity->setUniqueIdentifier('test');
                    if (!$criteria->getAggregation('tax')) {
                        return new AggregatorResult(
                            new AggregationResultCollection(
                                [
                                    new AggregationResult(
                                        new EntityAggregation(
                                            'product.datasheet.id',
                                            ConfigurationGroupOptionDefinition::class,
                                            'datasheet'
                                        ),
                                        [
                                            [
                                                'key' => null,
                                                'values' => new EntityCollection([$configGroupEntity]),
                                            ],
                                        ]
                                    ),
                                    new AggregationResult(
                                        new EntityAggregation(
                                            'product.manufacturer.id',
                                            ProductManufacturerDefinition::class,
                                            'manufacturer'
                                        ),
                                        [
                                            [
                                                'key' => null,
                                                'values' => new EntityCollection([$manufacturerEntity]),
                                            ],
                                        ]
                                    ),
                                    new AggregationResult(
                                        new StatsAggregation('product.listingPrices', 'price', false),
                                        []
                                    ),
                                ]
                            ),
                            $context,
                            $criteria
                        );
                    }

                    return new AggregatorResult(
                        new AggregationResultCollection(
                            [
                                new AggregationResult(
                                    new StatsAggregation('product.tax', 'tax', false),
                                    []
                                ),
                            ]
                        ),
                        $context,
                        $criteria
                    );
                }
            );

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityAggregator($this->cache, $dbalReader, $generator, true, 3600);

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->aggregate(TaxDefinition::class, $criteria, $context);

        //second call should hit the cache items and the dbal reader shouldn't be called
        $cachedEntities = $cachedReader->aggregate(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);

        //third call should hit the cache items and read one missing from dbal
        $cachedEntities = $cachedReader->aggregate(TaxDefinition::class, $criteria2, $context);

        static::assertNotEquals($databaseEntities->getAggregations(), $cachedEntities->getAggregations());
    }

    public function testDisableCacheOption()
    {
        $dbalReader = $this->createMock(EntityAggregator::class);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $criteria = new Criteria([$id1, $id2]);

        $datasheetAggregation = new EntityAggregation('product.datasheet.id', ConfigurationGroupOptionDefinition::class, 'datasheet');
        $criteria->addAggregation($datasheetAggregation);

        $manufacturerAggregation = new EntityAggregation('product.manufacturer.id', ProductManufacturerDefinition::class, 'manufacturer');
        $criteria->addAggregation($manufacturerAggregation);

        $priceAggregation = new StatsAggregation('product.listingPrices', 'price', false);
        $criteria->addAggregation($priceAggregation);

        $context = Context::createDefaultContext();

        $configGroupEntity = new ConfigurationGroupOptionEntity();
        $configGroupEntity->setUniqueIdentifier('test');

        $manufacturerEntity = new ProductManufacturerEntity();
        $manufacturerEntity->setUniqueIdentifier('test');

        //read in EntityReader will be only called once
        $dbalReader->expects(static::atLeast(2))
            ->method('aggregate')
            ->willReturn(
                new AggregatorResult(
                    new AggregationResultCollection(
                        [
                            new AggregationResult(
                                $datasheetAggregation,
                                [
                                    [
                                        'key' => null,
                                        'values' => new EntityCollection([$configGroupEntity]),
                                    ],
                                ]
                            ),
                            new AggregationResult(
                                $manufacturerAggregation,
                                [
                                    [
                                        'key' => null,
                                        'values' => new EntityCollection([$manufacturerEntity]),
                                    ],
                                ]
                            ),
                            new AggregationResult($priceAggregation, []),
                        ]
                    ),
                    $context,
                    $criteria
                )
            );

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityAggregator($this->cache, $dbalReader, $generator, false, 3600);

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->aggregate(TaxDefinition::class, $criteria, $context);

        //cache is disabled. second call shouldn't hit the cache and the dbal reader should be called
        $cachedEntities = $cachedReader->aggregate(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);
    }
}
