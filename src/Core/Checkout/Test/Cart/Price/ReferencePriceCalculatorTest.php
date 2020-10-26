<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class ReferencePriceCalculatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var GrossPriceCalculator
     */
    private $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = $this->getContainer()->get(GrossPriceCalculator::class);
    }

    public function testNoReferencePriceWithoutDefinition(): void
    {
        $definition = new QuantityPriceDefinition(123.3, new TaxRuleCollection());

        $referencePrice = $this->calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertNull($referencePrice->getReferencePrice());
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

        $quantityPriceDefinition = new QuantityPriceDefinition($price, new TaxRuleCollection());
        $quantityPriceDefinition->setReferencePriceDefinition($referencePriceDefinition);
        $quantityPriceDefinition->setIsCalculated(false);

        $referencePrice = $this->calculator->calculate($quantityPriceDefinition, new CashRoundingConfig(2, 0.01, true));

        $expectedReferencePrice = new ReferencePrice(
            $expectedPrice,
            $referencePriceDefinition->getPurchaseUnit(),
            $referencePriceDefinition->getReferenceUnit(),
            $referencePriceDefinition->getUnitName()
        );

        static::assertEquals($expectedReferencePrice, $referencePrice->getReferencePrice());
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
