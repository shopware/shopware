<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
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

    public function testICanCreateNestedAggregations(): void
    {
        $criteria = new Criteria();
        $exception = new SearchRequestException();

        $this->parser->buildAggregations(
            $this->getContainer()->get(ProductDefinition::class),
            [
                'aggregations' => [
                    [
                        'name' => 'level1',
                        'type' => 'terms',
                        'field' => 'product.manufacturerId',
                        'aggregation' => [
                            'name' => 'level2',
                            'type' => 'terms',
                            'limit' => 10,
                            'field' => 'product.manufacturerId',
                            'aggregation' => [
                                'name' => 'level3',
                                'type' => 'terms',
                                'field' => 'product.manufacturerId',
                                'sort' => [
                                    'field' => 'product.price',
                                    'direction' => FieldSorting::ASCENDING,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $criteria,
            $exception
        );

        static::assertCount(0, $exception->getErrors());
        static::assertCount(1, $criteria->getAggregations());

        $level = $criteria->getAggregation('level1');
        /** @var TermsAggregation $level */
        static::assertInstanceOf(TermsAggregation::class, $level);

        $level = $level->getAggregation();
        /** @var TermsAggregation $level */
        static::assertInstanceOf(TermsAggregation::class, $level);
        static::assertSame('level2', $level->getName());
        static::assertSame(10, $level->getLimit());

        $level = $level->getAggregation();
        /** @var TermsAggregation $level */
        static::assertInstanceOf(TermsAggregation::class, $level);
        static::assertSame('level3', $level->getName());
        static::assertEquals(new FieldSorting('product.price'), $level->getSorting());
    }

    public function testICanCreateAFilterAggregation(): void
    {
        $criteria = new Criteria();
        $exception = new SearchRequestException();

        $this->parser->buildAggregations(
            $this->getContainer()->get(ProductDefinition::class),
            [
                'aggregations' => [
                    [
                        'name' => 'filter_test',
                        'type' => 'filter',
                        'filter' => [
                            ['type' => 'contains', 'field' => 'foo', 'value' => 'bar'],
                            ['type' => 'equalsAny', 'field' => 'foo', 'value' => 'bar'],
                        ],
                        'aggregation' => [
                            'name' => 'level1',
                            'type' => 'terms',
                            'field' => 'product.manufacturerId',
                        ],
                    ],
                ],
            ],
            $criteria,
            $exception
        );

        static::assertCount(0, $exception->getErrors());
        static::assertCount(1, $criteria->getAggregations());

        /** @var FilterAggregation|null $aggregation */
        $aggregation = $criteria->getAggregation('filter_test');

        static::assertInstanceOf(FilterAggregation::class, $aggregation);
        static::assertCount(2, $aggregation->getFilter());

        $filters = $aggregation->getFilter();
        $filter = array_shift($filters);

        static::assertInstanceOf(ContainsFilter::class, $filter);
        static::assertSame('product.foo', $filter->getField());
        static::assertSame('bar', $filter->getValue());

        $filter = array_shift($filters);

        static::assertInstanceOf(EqualsAnyFilter::class, $filter);
        static::assertSame('product.foo', $filter->getField());
        static::assertSame(['bar'], $filter->getValue());
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
        static::assertEquals(ProductManufacturerDefinition::ENTITY_NAME, $entity->getEntity());
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

    public function testICanCreateARangeAggregation(): void
    {
        $criteria = new Criteria();
        $exception = new SearchRequestException();

        $expectedRanges = [['from' => 1.0, 'to' => 2.0], ['from' => 2.0, 'to' => 3.0]];

        $this->parser->buildAggregations(
            $this->getContainer()->get(ProductDefinition::class),
            [
                'aggregations' => [
                    [
                        'name' => 'range_test',
                        'type' => 'range',
                        'field' => 'product.manufacturerId',
                        'ranges' => $expectedRanges,
                    ],
                ],
            ],
            $criteria,
            $exception
        );

        static::assertCount(0, $exception->getErrors());
        static::assertCount(1, $criteria->getAggregations());

        $agg = $criteria->getAggregation('range_test');

        /** @var RangeAggregation $agg */
        static::assertInstanceOf(RangeAggregation::class, $agg);
        $computedRanges = $agg->getRanges();

        static::assertEquals($expectedRanges[0] + ['key' => '1-2'], $computedRanges[0]);
        static::assertEquals($expectedRanges[1] + ['key' => '2-3'], $computedRanges[1]);
    }
}
