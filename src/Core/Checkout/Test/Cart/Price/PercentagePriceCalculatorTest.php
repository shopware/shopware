<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;

class PercentagePriceCalculatorTest extends TestCase
{
    /**
     * @dataProvider grossPriceDataProvider
     */
    public function testCalculatePercentagePriceOfGrossPrices(PercentageCalculation $calculation): void
    {
        $taxCalculator = new TaxCalculator();
        $rounding = new CashRounding();
        $calculator = new PercentagePriceCalculator(
            $rounding,
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, $rounding),
                new NetPriceCalculator($taxCalculator, $rounding),
                new TaxDetector()
            ),
            new PercentageTaxRuleBuilder()
        );

        $price = $calculator->calculate(
            $calculation->getPercentageDiscount(),
            $calculation->getPrices(),
            Generator::createSalesChannelContext()
        );
        $expected = $calculation->getExpected();

        static::assertEquals($expected, $price);
        static::assertEquals($expected->getCalculatedTaxes(), $price->getCalculatedTaxes());
        static::assertEquals($expected->getTaxRules(), $price->getTaxRules());
        static::assertEquals($expected->getTotalPrice(), $price->getTotalPrice());
        static::assertEquals($expected->getUnitPrice(), $price->getUnitPrice());
        static::assertEquals($expected->getQuantity(), $price->getQuantity());
    }

    public function grossPriceDataProvider(): \Generator
    {
        yield [$this->getDifferentTaxesCalculation()];
        yield [$this->getOneHundredPercentageCalculation()];
        yield [$this->getFiftyPercentageCalculation()];
        yield [$this->regression_next_12270()];
    }

    private function regression_next_12270(): PercentageCalculation
    {
        $calculator = $this->createQuantityPriceCalculator();

        $priceDefinition = new QuantityPriceDefinition(10.40, new TaxRuleCollection([new TaxRule(21, 100)]), 1, 1, true);
        $price = $calculator->calculate($priceDefinition, Generator::createSalesChannelContext());
        static::assertSame(10.40, $price->getTotalPrice());
        static::assertSame(1.80, $price->getCalculatedTaxes()->getAmount());

        $priceDefinition = new QuantityPriceDefinition(104.00, new TaxRuleCollection([new TaxRule(21, 100)]), 1, 1, true);
        $price = $calculator->calculate($priceDefinition, Generator::createSalesChannelContext());
        static::assertSame(104.00, $price->getTotalPrice());
        static::assertSame(18.05, $price->getCalculatedTaxes()->getAmount());

        return new PercentageCalculation(
            -10,
            new CalculatedPrice(
                -10.4,
                -10.4,
                new CalculatedTaxCollection([
                    new CalculatedTax(-1.80, 21, -10.4),
                ]),
                new TaxRuleCollection([new TaxRule(21)])
            ),
            new PriceCollection([$price])
        );
    }

    private function getFiftyPercentageCalculation(): PercentageCalculation
    {
        $calculator = $this->createQuantityPriceCalculator();

        $priceDefinition = new QuantityPriceDefinition(100.00, new TaxRuleCollection([new TaxRule(20, 100)]), 5);

        $price = $calculator->calculate($priceDefinition, Generator::createSalesChannelContext());
        static::assertSame(500.00, $price->getTotalPrice());
        static::assertSame(83.33, $price->getCalculatedTaxes()->getAmount());

        return new PercentageCalculation(
            -50,
            new CalculatedPrice(
                -250,
                -250,
                new CalculatedTaxCollection([
                    new CalculatedTax(-41.67, 20, -250),
                ]),
                new TaxRuleCollection([new TaxRule(20)])
            ),
            new PriceCollection([$price])
        );
    }

    private function getOneHundredPercentageCalculation(): PercentageCalculation
    {
        $calculator = $this->createQuantityPriceCalculator();

        $priceDefinition = new QuantityPriceDefinition(29.00, new TaxRuleCollection([new TaxRule(17, 100)]), 10);

        $price = $calculator->calculate($priceDefinition, Generator::createSalesChannelContext());

        return new PercentageCalculation(
            -100,
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

    private function getDifferentTaxesCalculation(): PercentageCalculation
    {
        $calculator = $this->createQuantityPriceCalculator();

        $definition = new QuantityPriceDefinition(30, new TaxRuleCollection([new TaxRule(19)]));
        $price1 = $calculator->calculate($definition, Generator::createSalesChannelContext());

        $definition = new QuantityPriceDefinition(30, new TaxRuleCollection([new TaxRule(7)]));
        $price2 = $calculator->calculate($definition, Generator::createSalesChannelContext());

        return new PercentageCalculation(
            -10,
            new CalculatedPrice(
                -6.0,
                -6,
                new CalculatedTaxCollection([
                    new CalculatedTax(-0.48, 19, -3.0),
                    new CalculatedTax(-0.20, 7, -3.0),
                ]),
                new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)])
            ),
            new PriceCollection([$price1, $price2])
        );
    }

    private function createQuantityPriceCalculator(): QuantityPriceCalculator
    {
        $rounding = new CashRounding();
        $taxCalculator = new TaxCalculator();

        return new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $rounding),
            new NetPriceCalculator($taxCalculator, $rounding),
            Generator::createGrossPriceDetector()
        );
    }
}

class PercentageCalculation
{
    /**
     * @var float
     */
    protected $percentageDiscount;

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
        $this->percentageDiscount = $discount;
        $this->expected = $expected;
        $this->prices = $prices;
    }

    public function getPercentageDiscount(): float
    {
        return $this->percentageDiscount;
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
