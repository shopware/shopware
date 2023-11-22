<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;

/**
 * @internal
 */
#[CoversClass(DateHistogramAggregation::class)]
class DateHistogramAggregationTest extends TestCase
{
    public function testEncode(): void
    {
        $aggregation = new DateHistogramAggregation(
            'test',
            'test',
            DateHistogramAggregation::PER_DAY,
            null,
            null,
            null,
            null
        );

        static::assertEquals([
            'extensions' => [],
            'name' => 'test',
            'field' => 'test',
            'interval' => 'day',
            'aggregation' => null,
            '_class' => DateHistogramAggregation::class,
        ], $aggregation->jsonSerialize());
    }

    public function testClone(): void
    {
        $aggregation = new DateHistogramAggregation(
            'test',
            'test',
            DateHistogramAggregation::PER_DAY,
            null,
            null,
            null,
            null
        );

        $clone = clone $aggregation;

        static::assertEquals($aggregation->jsonSerialize(), $clone->jsonSerialize());
    }
}
