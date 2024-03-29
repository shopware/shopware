<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal
 */
#[CoversClass(FilterAggregation::class)]
class FilterAggregationTest extends TestCase
{
    public function testPassRealField(): void
    {
        // this test ensures, that the filter aggregation return the "FIELD" of the internal aggregation
        // this is required for the DAL to identify, which "REAL" field will be selected in the query to build the JOIN conditions correctly
        $aggregation = new FilterAggregation('foo', new TermsAggregation('foo', 'product.name'), []);

        static::assertSame('product.name', $aggregation->getField());
    }

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
