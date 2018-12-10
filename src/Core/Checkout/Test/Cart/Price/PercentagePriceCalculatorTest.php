<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleCalculator;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;

class PercentagePriceCalculatorTest extends TestCase
{
    /**
     * @dataProvider calculatePercentagePriceOfGrossPricesProvider
     *
     * @param float           $percentage
     * @param CalculatedPrice $expected
     * @param PriceCollection $prices
     */
    public function testCalculatePercentagePriceOfGrossPrices($percentage, CalculatedPrice $expected, PriceCollection $prices): void
    {
        $rounding = new PriceRounding(2);

        $taxCalculator = new TaxCalculator(
            new PriceRounding(2),
            [
                new TaxRuleCalculator($rounding),
                new PercentageTaxRuleCalculator(new TaxRuleCalculator($rounding)),
            ]
        );

        $calculator = new PercentagePriceCalculator(
            new PriceRounding(2),
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, new PriceRounding(2)),
                new NetPriceCalculator($taxCalculator, new PriceRounding(2)),
                Generator::createGrossPriceDetector()
            ),
            new PercentageTaxRuleBuilder()
        );

        $price = $calculator->calculate(
            $percentage,
            $prices,
            Generator::createCheckoutContext()
        );
        static::assertEquals($expected, $price);
        static::assertEquals($expected->getCalculatedTaxes(), $price->getCalculatedTaxes());
        static::assertEquals($expected->getTaxRules(), $price->getTaxRules());
        static::assertEquals($expected->getTotalPrice(), $price->getTotalPrice());
        static::assertEquals($expected->getUnitPrice(), $price->getUnitPrice());
        static::assertEquals($expected->getQuantity(), $price->getQuantity());
    }

    public function calculatePercentagePriceOfGrossPricesProvider(): array
    {
        $highTax = new TaxRuleCollection([new TaxRule(19)]);
        //prices of cart line items
        $prices = new PriceCollection([
            new CalculatedPrice(30.00, 30.00, new CalculatedTaxCollection([new CalculatedTax(4.79, 19, 30.00)]), $highTax),
            new CalculatedPrice(30.00, 30.00, new CalculatedTaxCollection([new CalculatedTax(1.96, 7, 30.00)]), $highTax),
        ]);

        return [
            [
                //10% discount
                -10,
                //expected calculated "discount" price
                new CalculatedPrice(
                    -6.0,
                    -6.0,
                    new CalculatedTaxCollection([
                        new CalculatedTax(-0.48, 19, -3.0),
                        new CalculatedTax(-0.20, 7, -3.0),
                    ]),
                    new TaxRuleCollection([
                        new PercentageTaxRule(19, 50),
                        new PercentageTaxRule(7, 50),
                    ]),
                    1
                ),
                $prices,
            ],
        ];
    }
}
