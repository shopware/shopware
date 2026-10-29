<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleShares;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TaxRuleShares::class)]
class TaxRuleSharesTest extends TestCase
{
    public function testEqualNetSharesTurnIntoTheGrossSharesOfTheirTaxedAmounts(): void
    {
        $shares = TaxRuleShares::ofGross(new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)]));

        static::assertEqualsWithDelta(59.5 / 113 * 100, $shares->get(19)?->getPercentage(), 0.000001);
        static::assertEqualsWithDelta(53.5 / 113 * 100, $shares->get(7)?->getPercentage(), 0.000001);
    }

    public function testEqualGrossSharesTurnIntoTheNetSharesTheirTaxesLeave(): void
    {
        $shares = TaxRuleShares::ofNet(new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)]));

        $net19 = 50 / 1.19;
        $net7 = 50 / 1.07;

        static::assertEqualsWithDelta($net19 / ($net19 + $net7) * 100, $shares->get(19)?->getPercentage(), 0.000001);
        static::assertEqualsWithDelta($net7 / ($net19 + $net7) * 100, $shares->get(7)?->getPercentage(), 0.000001);
    }

    public function testTheGrossSharesSplitOffExactlyTheTaxesTheNetSharesAdded(): void
    {
        $calculator = new TaxCalculator();
        $netShares = new TaxRuleCollection([new TaxRule(19, 30), new TaxRule(7, 60)]);

        $gross = $calculator->calculateGross(100.0, $netShares);
        $containedTax = $calculator->calculateGrossTaxes($gross, TaxRuleShares::ofGross($netShares))->getAmount();

        static::assertEqualsWithDelta(100.0, $gross - $containedTax, 0.000001);
    }

    public function testConvertingBackAndForthRestoresTheShares(): void
    {
        $netShares = new TaxRuleCollection([new TaxRule(19, 30), new TaxRule(7, 60)]);

        $restored = TaxRuleShares::ofNet(TaxRuleShares::ofGross($netShares));

        static::assertEqualsWithDelta(30.0, $restored->get(19)?->getPercentage(), 0.000001);
        static::assertEqualsWithDelta(60.0, $restored->get(7)?->getPercentage(), 0.000001);
    }

    public function testTheUntaxedRemainderKeepsItsPartOfThePrice(): void
    {
        $grossShares = TaxRuleShares::ofGross(new TaxRuleCollection([new TaxRule(19, 50)]));

        static::assertEqualsWithDelta(59.5 / 109.5 * 100, $grossShares->get(19)?->getPercentage(), 0.000001);
    }

    public function testASingleFullRuleStaysAFullRule(): void
    {
        $rules = new TaxRuleCollection([new TaxRule(7.7)]);

        static::assertSame(100.0, TaxRuleShares::ofGross($rules)->get('7.7')?->getPercentage());
        static::assertSame(100.0, TaxRuleShares::ofNet($rules)->get('7.7')?->getPercentage());
    }

    public function testNoRulesStayNoRules(): void
    {
        static::assertCount(0, TaxRuleShares::ofGross(new TaxRuleCollection()));
        static::assertCount(0, TaxRuleShares::ofNet(new TaxRuleCollection()));
    }
}
