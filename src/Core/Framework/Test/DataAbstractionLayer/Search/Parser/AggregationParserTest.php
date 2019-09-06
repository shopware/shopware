<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class AggregationParserTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var AggregationParser
     */
    private $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = $this->getContainer()->get(AggregationParser::class);
    }

    public function testWithUnsupportedFormat(): void
    {
        $this->expectException(InvalidAggregationQueryException::class);
        $criteria = new Criteria();
        $this->parser->buildAggregations(
            $this->getContainer()->get(ProductDefinition::class),
            ['aggregations' => 'foo'],
            $criteria,
            new SearchRequestException()
        );
    }

    public function testBuildAggregations(): void
    {
        $criteria = new Criteria();
        $exception = new SearchRequestException();
        $this->parser->buildAggregations(
            $this->getContainer()->get(ProductDefinition::class),
            [
                'aggregations' => [
                    [
                        'name' => 'max_agg',
                        'type' => 'max',
                        'field' => 'tax.taxRate',
                    ],
                    [
                        'name' => 'avg_agg',
                        'type' => 'avg',
                        'field' => 'stock',
                    ],
                ],
            ],
            $criteria,
            $exception
        );
        static::assertCount(0, $exception->getErrors());
        static::assertCount(2, $criteria->getAggregations());

        $maxAggregation = $criteria->getAggregation('max_agg');
        static::assertInstanceOf(MaxAggregation::class, $maxAggregation);
        static::assertEquals('max_agg', $maxAggregation->getName());
        static::assertEquals('product.tax.taxRate', $maxAggregation->getField());

        $avgAggregation = $criteria->getAggregation('avg_agg');
        static::assertInstanceOf(AvgAggregation::class, $avgAggregation);
        static::assertEquals('avg_agg', $avgAggregation->getName());
        static::assertEquals('product.stock', $avgAggregation->getField());
    }

    public function testBuildAggregationsWithSameName(): void
    {
        $criteria = new Criteria();
        $exception = new SearchRequestException();
        $this->parser->buildAggregations(
            $this->getContainer()->get(ProductDefinition::class),
            [
                'aggregations' => [
                    [
                        'name' => 'max',
                        'type' => 'max',
                        'field' => 'tax.taxRate',
                    ],
                    [
                        'name' => 'avg',
                        'type' => 'avg',
                        'field' => 'stock',
                    ],
                ],
            ],
            $criteria,
            $exception
        );
        static::assertCount(0, $exception->getErrors());
        static::assertCount(2, $criteria->getAggregations());

        $maxAggregation = $criteria->getAggregation('max');
        static::assertInstanceOf(MaxAggregation::class, $maxAggregation);
        static::assertEquals('max', $maxAggregation->getName());
        static::assertEquals('product.tax.taxRate', $maxAggregation->getField());

        $avgAggregation = $criteria->getAggregation('avg');
        static::assertInstanceOf(AvgAggregation::class, $avgAggregation);
        static::assertEquals('avg', $avgAggregation->getName());
        static::assertEquals('product.stock', $avgAggregation->getField());
    }

    public function testBuildAggregationsWithGroupBy(): void
    {
        $criteria = new Criteria();
        $exception = new SearchRequestException();
        $this->parser->buildAggregations(
            $this->getContainer()->get(ProductDefinition::class),
            [
                'aggregations' => [
                    [
                        'name' => 'max',
                        'type' => 'max',
                        'field' => 'tax.taxRate',
                        'groupByFields' => [
                            'product.tax.name',
                            'product.tax.id',
                        ],
                    ],
                ],
            ],
            $criteria,
            $exception
        );
        static::assertCount(0, $exception->getErrors());
        static::assertCount(1, $criteria->getAggregations());

        $maxAggregation = $criteria->getAggregation('max');
        static::assertInstanceOf(MaxAggregation::class, $maxAggregation);
        static::assertEquals('max', $maxAggregation->getName());
        static::assertEquals('product.tax.taxRate', $maxAggregation->getField());
        static::assertEquals(['product.tax.name', 'product.tax.id'], $maxAggregation->getGroupByFields());
    }

    public function testICanCreateAnEntityAggregation(): void
    {
        $criteria = new Criteria();
        $exception = new SearchRequestException();

        $this->parser->buildAggregations(
            $this->getContainer()->get(ProductDefinition::class),
            [
                'aggregations' => [
                    [
                        'name' => 'entity_test',
                        'type' => 'entity',
                        'field' => 'product.manufacturerId',
                        'definition' => 'product_manufacturer',
                    ],
                ],
            ],
            $criteria,
            $exception
        );

        static::assertCount(0, $exception->getErrors());
        static::assertCount(1, $criteria->getAggregations());

        $entity = $criteria->getAggregation('entity_test');

        /** @var EntityAggregation $entity */
        static::assertInstanceOf(EntityAggregation::class, $entity);
        static::assertEquals('product.manufacturerId', $entity->getField());
        static::assertEquals(ProductManufacturerDefinition::class, $entity->getDefinition());
    }

    public function testThrowExceptionByEntityAggregationWithoutDefinition(): void
    {
        $criteria = new Criteria();
        $exception = new SearchRequestException();

        $this->parser->buildAggregations(
            $this->getContainer()->get(ProductDefinition::class),
            [
                'aggregations' => [
                    [
                        'name' => 'entity_test',
                        'type' => 'entity',
                        'field' => 'manufacturerId',
                    ],
                ],
            ],
            $criteria,
            $exception
        );

        static::assertCount(1, $exception->getErrors());
        static::assertCount(0, $criteria->getAggregations());
    }
}
