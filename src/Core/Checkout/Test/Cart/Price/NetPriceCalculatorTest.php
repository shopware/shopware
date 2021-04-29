<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class NetPriceCalculatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider referencePriceCalculationProvider
     */
    public function testReferencePriceCalculation(?ReferencePriceDefinition $reference, ?ReferencePrice $expected): void
    {
        $definition = new QuantityPriceDefinition(100, new TaxRuleCollection(), 1);
        $definition->setReferencePriceDefinition($reference);

        $price = $this->getContainer()
            ->get(NetPriceCalculator::class)
            ->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertEquals($expected, $price->getReferencePrice());
    }

    public function referencePriceCalculationProvider(): \Generator
    {
        yield 'test calculation without reference price' => [
            null,
            null,
        ];

        yield 'test calculation with 0 purchase unit' => [
            new ReferencePriceDefinition(0, 1, 'test'),
            null,
        ];

        yield 'test calculation with 0 reference unit' => [
            new ReferencePriceDefinition(1, 0, 'test'),
            null,
        ];

        yield 'test calculation with all requirements fulfilled' => [
            new ReferencePriceDefinition(1, 1, 'test'),
            new ReferencePrice(100, 1, 1, 'test'),
        ];
    }
}
