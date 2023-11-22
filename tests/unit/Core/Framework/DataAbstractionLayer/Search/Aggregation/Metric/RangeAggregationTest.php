<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;

/**
 * @internal
 */
#[CoversClass(RangeAggregation::class)]
class RangeAggregationTest extends TestCase
{
    public function testEncode(): void
    {
        $aggregation = new RangeAggregation('foo', 'bar', [['to' => 100]]);

        static::assertEquals([
            'name' => 'foo',
            'extensions' => [],
            'field' => 'bar',
            'ranges' => [
                [
                    'to' => 100.0,
                    'from' => null,
                    'key' => '*-100',
                ],
            ],
            '_class' => RangeAggregation::class,
        ], $aggregation->jsonSerialize());
    }

    public function testClone(): void
    {
        $aggregation = new RangeAggregation('foo', 'bar', [['to' => 100]]);
        $clone = clone $aggregation;

        static::assertEquals('foo', $clone->getName());
        static::assertEquals('bar', $clone->getField());
        static::assertEquals($aggregation->jsonSerialize(), $clone->jsonSerialize());
    }
}
