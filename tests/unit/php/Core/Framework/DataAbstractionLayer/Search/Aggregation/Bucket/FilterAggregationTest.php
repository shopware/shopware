<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation
 */
class FilterAggregationTest extends TestCase
{
    public function testEncode(): void
    {
        $aggregation = new FilterAggregation('foo', new TermsAggregation('foo', 'name'), [new EqualsFilter('name', 'test')]);
        static::assertEquals([
            'name' => 'foo',
            'extensions' => [],
            'field' => '',
            'aggregation' => [
                'extensions' => [],
                'name' => 'foo',
                'field' => 'name',
                'aggregation' => null,
                'limit' => null,
                'sorting' => null,
                '_class' => TermsAggregation::class,
            ],
            'filter' => [
                [
                    'field' => 'name',
                    'value' => 'test',
                    'extensions' => [],
                    'isPrimary' => false,
                    'resolved' => null,
                    '_class' => EqualsFilter::class,
                ],
            ],
            '_class' => FilterAggregation::class,
        ], json_decode(json_encode($aggregation->jsonSerialize(), \JSON_THROW_ON_ERROR), true));
    }

    public function testClone(): void
    {
        $aggregation = new FilterAggregation('foo', new TermsAggregation('foo', 'name'), [new EqualsFilter('name', 'test')]);
        $clone = clone $aggregation;
        static::assertEquals($aggregation->getName(), $clone->getName());
        static::assertEquals($aggregation->getAggregation(), $clone->getAggregation());
        static::assertEquals($aggregation->getFilter(), $clone->getFilter());
        static::assertEquals($aggregation->jsonSerialize(), $clone->jsonSerialize());
    }
}
