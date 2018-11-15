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
                    'max_agg' => [
                        'max' => ['field' => 'tax.taxRate'],
                    ],
                    'avg_agg' => [
                        'avg' => ['field' => 'stock'],
                    ],
                ],
            ],
            $criteria,
            $exception
        );
        static::assertCount(0, $exception->getErrors());
        static::assertCount(2, $criteria->getAggregations());

        $maxAggregation = $criteria->getAggregations()[0];
        static::assertInstanceOf(MaxAggregation::class, $maxAggregation);
        static::assertEquals('max_agg', $maxAggregation->getName());
        static::assertEquals('product.tax.taxRate', $maxAggregation->getField());

        $avgAggregation = $criteria->getAggregations()[1];
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
                    'agg' => [
                        'max' => ['field' => 'tax.taxRate'],
                        'avg' => ['field' => 'stock'],
                    ],
                ],
            ],
            $criteria,
            $exception
        );
        static::assertCount(0, $exception->getErrors());
        static::assertCount(2, $criteria->getAggregations());

        $maxAggregation = $criteria->getAggregations()[0];
        static::assertInstanceOf(MaxAggregation::class, $maxAggregation);
        static::assertEquals('agg', $maxAggregation->getName());
        static::assertEquals('product.tax.taxRate', $maxAggregation->getField());

        $avgAggregation = $criteria->getAggregations()[1];
        static::assertInstanceOf(AvgAggregation::class, $avgAggregation);
        static::assertEquals('agg', $avgAggregation->getName());
        static::assertEquals('product.stock', $avgAggregation->getField());
    }
}
