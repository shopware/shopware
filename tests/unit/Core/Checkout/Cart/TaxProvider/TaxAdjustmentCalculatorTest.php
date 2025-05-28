<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxAdjustmentCalculator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(TaxAdjustmentCalculator::class)]
#[Package('checkout')]
class TaxAdjustmentCalculatorTest extends TestCase
{
    public function testCalculateGrossTaxesActuallyCalculatesNetTaxes(): void
    {
        $taxes = (new TaxAdjustmentCalculator())->calculateGrossTaxes(100, new TaxRuleCollection([
            new TaxRule(20, 50),
            new TaxRule(10, 50),
        ]));

        $taxes = $taxes->getElements();

        static::assertCount(2, $taxes);
        static::assertArrayHasKey(20, $taxes);
        static::assertArrayHasKey(10, $taxes);

        static::assertSame(20.0, $taxes[20]->getTaxRate());
        static::assertSame(10.0, $taxes[10]->getTaxRate());

        static::assertSame(50.0, $taxes[20]->getPrice());
        static::assertSame(50.0, $taxes[10]->getPrice());

        static::assertSame(10.0, $taxes[20]->getTax());
        static::assertSame(5.0, $taxes[10]->getTax());
    }
}
