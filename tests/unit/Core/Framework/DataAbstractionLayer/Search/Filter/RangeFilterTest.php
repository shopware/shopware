<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter
 */
class RangeFilterTest extends TestCase
{
    public function testEncode(): void
    {
        $filter = new RangeFilter('foo', [
            RangeFilter::GT => 1,
        ]);

        static::assertEquals(
            [
                'field' => 'foo',
                'isPrimary' => false,
                'resolved' => null,
                'extensions' => [],
                'parameters' => [
                    RangeFilter::GT => 1,
                ],
                '_class' => RangeFilter::class,
            ],
            $filter->jsonSerialize()
        );
    }

    public function testClone(): void
    {
        $filter = new RangeFilter('foo', [
            RangeFilter::GT => 1,
        ]);
        $clone = clone $filter;

        static::assertEquals($filter->jsonSerialize(), $clone->jsonSerialize());
        static::assertEquals($filter->getField(), $clone->getField());
        static::assertEquals($filter->getFields(), $clone->getFields());
        static::assertNotSame($filter, $clone);
    }
}
