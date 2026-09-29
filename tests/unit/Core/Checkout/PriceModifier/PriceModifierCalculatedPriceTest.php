<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\PriceModifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCalculatedPrice;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceModifierCalculatedPrice::class)]
class PriceModifierCalculatedPriceTest extends TestCase
{
    public function testExposesConstructorValues(): void
    {
        $taxes = new CalculatedTaxCollection([new CalculatedTax(-7.98, 19.0, -50.0)]);
        $taxRules = new TaxRuleCollection([new TaxRule(19.0)]);

        $price = new PriceModifierCalculatedPrice(-50.0, $taxes, $taxRules);

        static::assertSame(-50.0, $price->getTotalPrice());
        static::assertSame($taxes, $price->getCalculatedTaxes());
        static::assertSame($taxRules, $price->getTaxRules());
        static::assertSame('cart_price_modifier_calculated_price', $price->getApiAlias());
    }

    public function testFromCalculatedPriceTakesTotalAndTaxBreakdown(): void
    {
        $taxes = new CalculatedTaxCollection([new CalculatedTax(1.6, 19.0, 10.0)]);
        $taxRules = new TaxRuleCollection([new TaxRule(19.0)]);

        $price = PriceModifierCalculatedPrice::fromCalculatedPrice(
            new CalculatedPrice(5.0, 10.0, $taxes, $taxRules, 2)
        );

        static::assertSame(10.0, $price->getTotalPrice());
        static::assertSame($taxes, $price->getCalculatedTaxes());
        static::assertSame($taxRules, $price->getTaxRules());
    }
}
