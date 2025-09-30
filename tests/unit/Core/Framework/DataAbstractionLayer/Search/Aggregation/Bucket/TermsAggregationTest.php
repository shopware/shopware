<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;

/**
 * @internal
 */
#[CoversClass(TermsAggregation::class)]
class TermsAggregationTest extends TestCase
{
    public function testEncode(): void
    {
        $aggregation = new TermsAggregation('foo', 'name');
        static::assertEquals([
            'name' => 'foo',
            'extensions' => [],
            'field' => 'name',
            'aggregation' => null,
            'limit' => null,
            'sorting' => null,
            '_class' => TermsAggregation::class,
        ], $aggregation->jsonSerialize());
    }

    public function testClone(): void
    {
        $aggregation = new TermsAggregation('foo', 'name');
        $clone = clone $aggregation;
        static::assertSame($aggregation->getName(), $clone->getName());
        static::assertSame($aggregation->getFields(), $clone->getFields());
        static::assertSame($aggregation->jsonSerialize(), $clone->jsonSerialize());
    }
}
