<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NotEqualsFilter::class)]
class NotEqualsFilterTest extends TestCase
{
    public function testCreatesNotEqualsFilterTest(): void
    {
        $filter = new NotEqualsFilter('product.name', 'test');

        static::assertSame('product.name', $filter->getField());
        static::assertSame('test', $filter->getValue());
        static::assertCount(1, $filter->getQueries());
        static::assertSame(
            [
                (new EqualsFilter('product.name', 'test'))->jsonSerialize(),
            ],
            array_map(
                static fn (Filter $filter): array => $filter->jsonSerialize(),
                $filter->getQueries()
            )
        );
    }
}
