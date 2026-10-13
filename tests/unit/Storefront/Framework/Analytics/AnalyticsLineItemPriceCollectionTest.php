<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Analytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineItemPrice;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineItemPriceCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AnalyticsLineItemPriceCollection::class)]
class AnalyticsLineItemPriceCollectionTest extends TestCase
{
    public function testHoldsThePricesByLineItemId(): void
    {
        $price = new AnalyticsLineItemPrice(6.67, 3.33, 20.0);
        $collection = new AnalyticsLineItemPriceCollection();
        $collection->set('line-item-1', $price);

        static::assertSame($price, $collection->get('line-item-1'));
        static::assertNull($collection->get('line-item-2'));
        static::assertSame('storefront_analytics_line_item_price_collection', $collection->getApiAlias());
    }

    public function testOnlyAcceptsAnalyticsPrices(): void
    {
        $this->expectException(FrameworkException::class);

        // @phpstan-ignore argument.type (the expected class check is what is tested)
        (new AnalyticsLineItemPriceCollection())->add(new \stdClass());
    }
}
