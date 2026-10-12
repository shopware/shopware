<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Analytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Analytics\AbstractAnalyticsLineItemPriceCalculator;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineItemPrice;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineItemPriceCalculator;
use Shopware\Storefront\Framework\Twig\Extension\AnalyticsLineItemPriceExtension;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractAnalyticsLineItemPriceCalculator::class)]
class AbstractAnalyticsLineItemPriceCalculatorTest extends TestCase
{
    public function testADecoratorReplacesThePricesTheTemplateReceives(): void
    {
        $decorator = new class(new AnalyticsLineItemPriceCalculator(new CashRounding())) extends AbstractAnalyticsLineItemPriceCalculator {
            public function __construct(private readonly AbstractAnalyticsLineItemPriceCalculator $decorated)
            {
            }

            public function getDecorated(): AbstractAnalyticsLineItemPriceCalculator
            {
                return $this->decorated;
            }

            public function calculate(?iterable $lineItems, SalesChannelContext $context): array
            {
                return $this->decorated->calculate($lineItems, $context)
                    + ['extension-line' => new AnalyticsLineItemPrice(1.0, 0.0, 1.0)];
            }
        };

        $function = (new AnalyticsLineItemPriceExtension($decorator))->getFunctions()[0];
        $callable = $function->getCallable();
        static::assertIsCallable($callable);

        $prices = $callable([], Generator::generateSalesChannelContext());

        static::assertEquals(['extension-line' => new AnalyticsLineItemPrice(1.0, 0.0, 1.0)], $prices);
        static::assertInstanceOf(AnalyticsLineItemPriceCalculator::class, $decorator->getDecorated());
    }
}
