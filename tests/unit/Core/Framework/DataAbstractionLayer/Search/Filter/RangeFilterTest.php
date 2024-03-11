<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidRangeFilterParamException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

/**
 * @internal
 */
#[CoversClass(RangeFilter::class)]
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

    /**
     * @param array<string, mixed> $filter
     */
    #[DataProvider('rangeFilterDataProvider')]
    public function testRangeFilterValidation(array $filter, ?Filter $expectedFilter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidRangeFilterParamException::class);
        }

        $result = new RangeFilter('foo', $filter); // @phpstan-ignore-line we call it with invalid params to check the error handling

        static::assertEquals($expectedFilter, $result);
    }

    public static function rangeFilterDataProvider(): \Generator
    {
        yield 'With empty value' => [['lte' => ''], null, true];

        yield 'With not suppoerted parameter key' => [['foo' => 3], null, true];

        yield 'With one parameter' => [['lte' => 3.0], new RangeFilter('foo', [RangeFilter::LTE => 3.0]), false];

        yield 'With multiple parameter' => [['lte' => 3.0, 'gte' => 1.5], new RangeFilter('foo', [RangeFilter::LTE => 3.0, RangeFilter::GTE => 1.5]), false];
    }
}
