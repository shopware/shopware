<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\TaxProvider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxAdjustment;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxAdjustmentCalculator;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class TaxAdjustmentTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUsesCorrectCalculator(): void
    {
        $adjustment = $this->getContainer()->get(TaxAdjustment::class);
        $ref = new \ReflectionClass(TaxAdjustment::class);

        static::assertTrue($ref->hasProperty('amountCalculator'));

        $calculator = $ref->getProperty('amountCalculator')->getValue($adjustment);

        static::assertInstanceOf(AmountCalculator::class, $calculator);

        $ref = new \ReflectionClass($calculator);

        static::assertTrue($ref->hasProperty('taxCalculator'));

        $taxCalculator = $ref->getProperty('taxCalculator')->getValue($calculator);

        static::assertInstanceOf(TaxAdjustmentCalculator::class, $taxCalculator);
    }
}
