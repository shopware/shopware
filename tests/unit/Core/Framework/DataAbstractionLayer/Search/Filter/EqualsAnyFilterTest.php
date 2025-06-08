<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

/**
 * @internal
 */
#[CoversClass(EqualsAnyFilter::class)]
class EqualsAnyFilterTest extends TestCase
{
    public function testEncode(): void
    {
        $filter = new EqualsAnyFilter('foo', ['bar']);

        static::assertEquals(
            [
                'field' => 'foo',
                'value' => ['bar'],
                'isPrimary' => false,
                'resolved' => null,
                'extensions' => [],
                '_class' => EqualsAnyFilter::class,
            ],
            $filter->jsonSerialize()
        );
    }

    public function testClone(): void
    {
        $filter = new EqualsAnyFilter('foo', ['bar']);
        $clone = clone $filter;

        static::assertSame($filter->jsonSerialize(), $clone->jsonSerialize());
        static::assertSame($filter->getField(), $clone->getField());
        static::assertSame($filter->getFields(), $clone->getFields());
        static::assertSame($filter->getValue(), $clone->getValue());
        static::assertNotSame($filter, $clone);
    }
}
