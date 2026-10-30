<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NotFilter::class)]
class NotFilterTest extends TestCase
{
    public function testNegatesMultipleQueriesWithTheGivenOperator(): void
    {
        $equalsFilter = new EqualsFilter('product.active', true);
        $rangeFilter = new RangeFilter('product.stock', [RangeFilter::GT => 0]);

        $filter = new NotFilter(NotFilter::CONNECTION_OR, [$equalsFilter, $rangeFilter]);

        static::assertSame(NotFilter::CONNECTION_OR, $filter->getOperator());
        static::assertSame([$equalsFilter, $rangeFilter], $filter->getQueries());
    }

    public function testNegatesASingleFilter(): void
    {
        $equalsFilter = new EqualsFilter('product.active', true);

        $filter = NotFilter::negate($equalsFilter);

        static::assertSame(NotFilter::CONNECTION_AND, $filter->getOperator());
        static::assertSame([$equalsFilter], $filter->getQueries());
        static::assertSame(['product.active'], $filter->getFields());
        static::assertEquals(new NotFilter(NotFilter::CONNECTION_AND, [$equalsFilter]), $filter);
    }

    public function testRejectsAnInvalidOperator(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Operator FOO not allowed'));

        new NotFilter('foo', [new EqualsFilter('product.active', true)]);
    }
}
