<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation
 */
class AvgAggregationTest extends TestCase
{
    public function testEncode(): void
    {
        $aggregation = new AvgAggregation('foo', 'bar');

        static::assertEquals([
            'name' => 'foo',
            'extensions' => [],
            'field' => 'bar',
            '_class' => AvgAggregation::class,
        ], $aggregation->jsonSerialize());
    }

    public function testClone(): void
    {
        $aggregation = new AvgAggregation('foo', 'bar');
        $clone = clone $aggregation;

        static::assertEquals('foo', $clone->getName());
        static::assertEquals('bar', $clone->getField());
        static::assertEquals($aggregation->jsonSerialize(), $clone->jsonSerialize());
    }
}
