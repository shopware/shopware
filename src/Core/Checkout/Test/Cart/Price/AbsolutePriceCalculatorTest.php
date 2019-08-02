<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\ReferencePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;

class AbsolutePriceCalculatorTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function testCalculateAbsolutePriceOfGrossPrices(AbsoluteCalculation $calculation): void
    {
        $rounding = new PriceRounding();

        $taxCalculator = new TaxCalculator(
            new TaxRuleCalculator()
        );

        $referencePriceCalculator = new ReferencePriceCalculator($rounding);

        $calculator = new AbsolutePriceCalculator(
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
                new NetPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
                Generator::createGrossPriceDetector(),
                $referencePriceCalculator
            ),
            new PercentageTaxRuleBuilder()
        );

        $calculatedPrice = $calculator->calculate(
            $calculation->getDiscount(),
            $calculation->getPrices(),
            Generator::createSalesChannelContext()
        );

        static::assertEquals($calculation->getExpected()->getCalculatedTaxes(), $calculatedPrice->getCalculatedTaxes());
        static::assertEquals($calculation->getExpected()->getTaxRules(), $calculatedPrice->getTaxRules());
        static::assertEquals($calculation->getExpected()->getTotalPrice(), $calculatedPrice->getTotalPrice());
        static::assertEquals($calculation->getExpected()->getUnitPrice(), $calculatedPrice->getUnitPrice());
        static::assertEquals($calculation->getExpected()->getQuantity(), $calculatedPrice->getQuantity());
    }

    public function provider(): array
    {
        return [
            'small-discounts' => [$this->getSmallDiscountCase()],
            '100%' => [$this->getOneHundredPercentageDiscountCase()],
        ];
    }

    private function getSmallDiscountCase(): AbsoluteCalculation
    {
        $calculator = $this->createQuantityPriceCalculator();

        $definition = new QuantityPriceDefinition(30, new TaxRuleCollection([new TaxRule(19)]), 2, 1, true);
        $price1 = $calculator->calculate($definition, Generator::createSalesChannelContext());

        $definition = new QuantityPriceDefinition(30, new TaxRuleCollection([new TaxRule(7)]), 2, 1, true);
        $price2 = $calculator->calculate($definition, Generator::createSalesChannelContext());

        return new AbsoluteCalculation(
            -6,
            new CalculatedPrice(
                -6,
                -6,
                new CalculatedTaxCollection([
                    new CalculatedTax(-0.48, 19, -3),
                    new CalculatedTax(-0.20, 7, -3),
                ]),
                new TaxRuleCollection([
                    new TaxRule(19, 50),
                    new TaxRule(7, 50),
                ]),
                1
            ),
            new PriceCollection([$price1, $price2])
        );
    }

    private function getOneHundredPercentageDiscountCase(): AbsoluteCalculation
    {
        $calculator = $this->createQuantityPriceCalculator();

        $priceDefinition = new QuantityPriceDefinition(29.00, new TaxRuleCollection([new TaxRule(17, 100)]), 2, 10, true);

        $price = $calculator->calculate($priceDefinition, Generator::createSalesChannelContext());

        return new AbsoluteCalculation(
            -290,
            new CalculatedPrice(
                -290,
                -290,
                new CalculatedTaxCollection([
                    new CalculatedTax(-42.14, 17, -290),
                ]),
                new TaxRuleCollection([new TaxRule(17)])
            ),
            new PriceCollection([$price])
        );
    }

    private function createQuantityPriceCalculator(): QuantityPriceCalculator
    {
        $rounding = new PriceRounding();
        $taxCalculator = new TaxCalculator(new TaxRuleCalculator());
        $referencePriceCalculator = new ReferencePriceCalculator($rounding);

        return new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
            new NetPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
            Generator::createGrossPriceDetector(),
            $referencePriceCalculator
        );
    }
}

class AbsoluteCalculation
{
    /**
     * @var float
     */
    protected $discount;

    /**
     * @var CalculatedPrice
     */
    protected $expected;

    /**
     * @var PriceCollection
     */
    protected $prices;

    public function __construct(float $discount, CalculatedPrice $expected, PriceCollection $prices)
    {
        $this->discount = $discount;
        $this->expected = $expected;
        $this->prices = $prices;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function getExpected(): CalculatedPrice
    {
        return $this->expected;
    }

    public function getPrices(): PriceCollection
    {
        return $this->prices;
    }
}
