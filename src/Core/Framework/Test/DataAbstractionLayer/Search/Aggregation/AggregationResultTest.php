<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;

class AggregationResultTest extends TestCase
{
    public function testCollectionMissingAggregationReturnsNull(): void
    {
        $collection = new AggregationResultCollection();

        static::assertNull($collection->get('foo'));
    }

    public function testCollectionGetExistingAggregationResult(): void
    {
        $aggregation = new CountAggregation('field', 'foo');
        $aggregationResult = new AggregationResult($aggregation, []);

        $collection = new AggregationResultCollection();
        $collection->add($aggregationResult);

        static::assertSame($aggregationResult, $collection->get('foo'));
        static::assertSame($aggregation, $collection->get('foo')->getAggregation());
    }

    public function testCollectionGetExistingAggregationResultWithNonEmptyCollection(): void
    {
        $aggregation = new CountAggregation('field', 'foo');
        $aggregationResult = new AggregationResult($aggregation, []);

        $collection = new AggregationResultCollection();
        $collection->add($aggregationResult);

        static::assertSame($aggregationResult, $collection->get('foo'));
        static::assertSame($aggregation, $collection->get('foo')->getAggregation());
        static::assertNull($collection->get('null_agg'));
    }

    public function testResultReturnsAggregationData(): void
    {
        $aggregation = new CountAggregation('field', 'foo');
        $aggregationResult = new \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult($aggregation, []);

        static::assertSame($aggregation, $aggregationResult->getAggregation());
        static::assertSame($aggregation->getName(), $aggregationResult->getName());
        static::assertSame($aggregation->getField(), $aggregationResult->getField());
        static::assertSame($aggregation->getFields(), [$aggregationResult->getField()]);
    }

    public function testResultByKey(): void
    {
        $aggregation = new CountAggregation('field', 'foo', 'foo.name');
        $aggregationResult = new \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult($aggregation, [
            [
                'key' => [
                    'foo.name' => 'test',
                ],
                'count' => 12,
            ],
        ]);

        static::assertEquals([
            'key' => [
                'foo.name' => 'test',
            ],
            'count' => 12,
        ], $aggregationResult->get(['foo.name' => 'test']));
    }

    public function testResultByKeyReturnsNull(): void
    {
        $aggregation = new CountAggregation('field', 'foo', 'foo.name');
        $aggregationResult = new \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult($aggregation, [
            [
                'key' => [
                    'foo.name' => 'test',
                ],
                'count' => 12,
            ],
        ]);

        static::assertNull($aggregationResult->get(['foo.name' => 'notFound']));
    }
}
