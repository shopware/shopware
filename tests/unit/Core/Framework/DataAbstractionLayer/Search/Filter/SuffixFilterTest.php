<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;

/**
 * @internal
 */
#[CoversClass(SuffixFilter::class)]
class SuffixFilterTest extends TestCase
{
    public function testEncode(): void
    {
        $filter = new SuffixFilter('foo', 'bar');

        static::assertEquals(
            [
                'field' => 'foo',
                'value' => 'bar',
                'isPrimary' => false,
                'resolved' => null,
                'extensions' => [],
                '_class' => SuffixFilter::class,
            ],
            $filter->jsonSerialize()
        );
    }

    public function testClone(): void
    {
        $filter = new SuffixFilter('foo', 'bar');
        $clone = clone $filter;

        static::assertEquals($filter->jsonSerialize(), $clone->jsonSerialize());
        static::assertEquals($filter->getField(), $clone->getField());
        static::assertEquals($filter->getFields(), $clone->getFields());
        static::assertEquals($filter->getValue(), $clone->getValue());
        static::assertNotSame($filter, $clone);
    }
}
