<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(PercentageTaxRuleBuilder::class)]
#[Package('checkout')]
class PercentageTaxRuleBuilderTest extends TestCase
{
    #[DataProvider('getCaseTestMatchValues')]
    public function testBuildCollectionRules(float $total, float $percentSeven, float $percentNineteen): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(0, 7, 50),
            new CalculatedTax(0, 19, 150),
        ]);

        $rules = (new PercentageTaxRuleBuilder())->buildCollectionRules($collection, $total);

        $seven = $rules->get('7');
        $nineteen = $rules->get('19');

        static::assertNotNull($seven);
        static::assertNotNull($nineteen);

        static::assertSame(7.0, $seven->getTaxRate());
        static::assertSame($percentSeven, $seven->getPercentage());
        static::assertSame(19.0, $nineteen->getTaxRate());
        static::assertSame($percentNineteen, $nineteen->getPercentage());
    }

    public function testBuildCollectionRulesFallsBackToFullAllocationWhenSingleRateNetsToZero(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(0, 19, 0),
        ]);

        $rules = (new PercentageTaxRuleBuilder())->buildCollectionRules($collection, 0);

        $rule = $rules->get('19');

        static::assertNotNull($rule);
        static::assertSame(19.0, $rule->getTaxRate());
        static::assertSame(100.0, $rule->getPercentage());
    }

    public function testBuildCollectionRulesReturnsEmptyCollectionWhenNoTaxes(): void
    {
        $rules = (new PercentageTaxRuleBuilder())->buildCollectionRules(new CalculatedTaxCollection(), 0);

        static::assertCount(0, $rules);
    }

    /**
     * @return array<string, array<float>>
     */
    public static function getCaseTestMatchValues(): array
    {
        return [
            'with total' => [200, 25.0, 75.0],
            'without total falls back to equal share' => [0, 50.0, 50.0],
        ];
    }
}
