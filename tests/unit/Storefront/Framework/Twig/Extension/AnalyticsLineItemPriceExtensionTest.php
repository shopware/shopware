<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Checkout\Cart\AnalyticsLineItemPriceCalculator;
use Shopware\Storefront\Framework\Twig\Extension\AnalyticsLineItemPriceExtension;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AnalyticsLineItemPriceExtension::class)]
class AnalyticsLineItemPriceExtensionTest extends TestCase
{
    public function testRegistersTheTwigFunction(): void
    {
        $extension = new AnalyticsLineItemPriceExtension(new AnalyticsLineItemPriceCalculator(new CashRounding()));

        $names = array_map(static fn ($function) => $function->getName(), $extension->getFunctions());

        static::assertSame(['sw_analytics_line_item_prices'], $names);
    }
}
