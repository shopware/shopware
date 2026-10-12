<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Analytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineAllocation;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AnalyticsLineAllocation::class)]
class AnalyticsLineAllocationTest extends TestCase
{
    public function testDiscountedTotalFollowsTheAllocatedDiscount(): void
    {
        $allocation = new AnalyticsLineAllocation(30.0, 3, 10.0);

        static::assertSame(30.0, $allocation->total);
        static::assertSame(3, $allocation->quantity);
        static::assertSame(20.0, $allocation->discountedTotal());

        // the calculator raises the discount when it spreads an overflow over the line
        $allocation->discount += 5.0;

        static::assertSame(15.0, $allocation->discountedTotal());
    }
}
