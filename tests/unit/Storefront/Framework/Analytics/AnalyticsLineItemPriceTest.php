<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Analytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineItemPrice;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AnalyticsLineItemPrice::class)]
class AnalyticsLineItemPriceTest extends TestCase
{
    public function testExposesTheReportedValues(): void
    {
        // 20.00 over three units: the unit price is rounded, the line total stays exact
        $price = new AnalyticsLineItemPrice(price: 6.67, discount: 3.33, total: 20.0);

        static::assertSame(6.67, $price->price);
        static::assertSame(3.33, $price->discount);
        static::assertSame(20.0, $price->total);
    }
}
