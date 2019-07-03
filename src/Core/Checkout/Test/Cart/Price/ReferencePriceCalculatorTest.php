<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\ReferencePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class ReferencePriceCalculatorTest extends TestCase
{
    /**
     * @var ReferencePriceCalculator
     */
    private $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ReferencePriceCalculator(new PriceRounding());
    }

    public function testNoReferencePriceWithoutDefinition(): void
    {
        $quantityPriceDefinition = new QuantityPriceDefinition(
            123.3,
            new TaxRuleCollection(),
            2
        );

        $referencePrice = $this->calculator->calculate(123.3, $quantityPriceDefinition);

        static::assertNull($referencePrice);
    }

    /**
     * @dataProvider calculationProvider
     */
    public function testReferencePriceCalculation(float $price, float $expectedPrice): void
    {
        $referencePriceDefinition = new ReferencePriceDefinition(
            0.7,
            1,
            'Liter'
        );

        $quantityPriceDefinition = new QuantityPriceDefinition(
            $price,
            new TaxRuleCollection(),
            2,
            1,
            false,
            $referencePriceDefinition
        );

        $referencePrice = $this->calculator->calculate($price, $quantityPriceDefinition);

        $expectedReferencePrice = new ReferencePrice(
            $expectedPrice,
            $referencePriceDefinition->getPurchaseUnit(),
            $referencePriceDefinition->getReferenceUnit(),
            $referencePriceDefinition->getUnitName()
        );

        static::assertEquals($expectedReferencePrice, $referencePrice);
    }

    public function calculationProvider(): array
    {
        return [
            [7, 10],
            [123.3, 176.14],
            [145.25146, 207.50],
        ];
    }
}
