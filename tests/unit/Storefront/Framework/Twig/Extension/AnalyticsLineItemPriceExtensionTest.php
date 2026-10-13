<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineItemPrice;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineItemPriceCalculator;
use Shopware\Storefront\Framework\Twig\Extension\AnalyticsLineItemPriceExtension;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AnalyticsLineItemPriceExtension::class)]
class AnalyticsLineItemPriceExtensionTest extends TestCase
{
    private AnalyticsLineItemPriceExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new AnalyticsLineItemPriceExtension(new AnalyticsLineItemPriceCalculator(new CashRounding()));
    }

    public function testRegistersTheTwigFunction(): void
    {
        $names = array_map(static fn ($function) => $function->getName(), $this->extension->getFunctions());

        static::assertSame(['sw_analytics_line_item_prices'], $names);
    }

    /**
     * The line items come from a template, so the extension does not trust their types.
     */
    public function testDropsEntriesThatAreNoLineItems(): void
    {
        $lineItem = new LineItem('product-1', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-1', 1);
        $lineItem->setGood(true);
        $lineItem->setPrice(new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection(), 1));

        $prices = $this->extension->getPrices(
            ['not a line item', ['id' => 'product-2'], new \stdClass(), $lineItem],
            Generator::generateSalesChannelContext()
        );

        static::assertEquals(['product-1' => new AnalyticsLineItemPrice(10.0, 0.0, 10.0)], $prices->getElements());
    }

    public function testReportsNoPricesForAValueThatIsNotIterable(): void
    {
        $context = Generator::generateSalesChannelContext();

        static::assertCount(0, $this->extension->getPrices(null, $context));
        static::assertCount(0, $this->extension->getPrices('not line items', $context));
    }
}
