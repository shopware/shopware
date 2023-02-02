<?php declare(strict_types=1);

namespace unit\php\Core\Checkout\Cart\TaxProvider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxAdjustmentCalculator;

/**
 * @package checkout
 *
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\TaxProvider\TaxAdjustmentCalculator
 */
class TaxAdjustmentCalculatorTest extends TestCase
{
    public function testCalculateGrossTaxesActuallyCalculatesNetTaxes(): void
    {
        $calculator = new TaxAdjustmentCalculator();

        $taxes = $calculator->calculateGrossTaxes(100, new TaxRuleCollection([
            new TaxRule(20, 50),
            new TaxRule(10, 50),
        ]));

        $taxes = $taxes->getElements();

        static::assertCount(2, $taxes);
        static::assertArrayHasKey(20, $taxes);
        static::assertArrayHasKey(10, $taxes);

        static::assertEquals(20, $taxes[20]->getTaxRate());
        static::assertEquals(10, $taxes[10]->getTaxRate());

        static::assertEquals(50, $taxes[20]->getPrice());
        static::assertEquals(50, $taxes[10]->getPrice());

        static::assertEquals(10, $taxes[20]->getTax());
        static::assertEquals(5, $taxes[10]->getTax());
    }
}
