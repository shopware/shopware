<?php declare(strict_types=1);

namespace src\Core\Framework\Test\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;

class AggregationParserTest extends TestCase
{
    public function testWithUnsupportedFormat(): void
    {
        $this->expectException(InvalidAggregationQueryException::class);
        $criteria = new Criteria();
        AggregationParser::buildAggregations(
            ProductDefinition::class,
            ['aggregations' => 'foo'],
            $criteria,
            new SearchRequestException()
        );
    }

    public function testBuildAggregations(): void
    {
        $criteria = new Criteria();
        $exception = new SearchRequestException();
        AggregationParser::buildAggregations(
            ProductDefinition::class,
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
        AggregationParser::buildAggregations(
            ProductDefinition::class,
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
}
