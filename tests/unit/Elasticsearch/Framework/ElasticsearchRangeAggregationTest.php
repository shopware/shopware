<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Framework\ElasticsearchRangeAggregation;

/**
 * @internal
 */
#[CoversClass(ElasticsearchRangeAggregation::class)]
class ElasticsearchRangeAggregationTest extends TestCase
{
    public function testElasticsearchAggregationBuild(): void
    {
        $ranges = [
            ['from' => 1, 'to' => 2],
            ['from' => 2, 'to' => 3],
            ['from' => 3, 'to' => 4],
        ];

        $agg = new ElasticsearchRangeAggregation('test-name', 'test-field', $ranges);

        static::assertEquals([
            'ranges' => [
                'field' => 'test-field',
                'ranges' => $ranges,
            ],
        ], $agg->toArray());
    }
}
