<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(QuantityPriceDefinition::class)]
class QuantityPriceDefinitionTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $taxRules = new TaxRuleCollection([new TaxRule(19.0)]);
        $definition = new QuantityPriceDefinition(9.99, $taxRules, 3);

        static::assertSame(9.99, $definition->getPrice());
        static::assertSame($taxRules, $definition->getTaxRules());
        static::assertSame(3, $definition->getQuantity());
        static::assertTrue($definition->isCalculated());
    }

    public function testQuantityDefaultsToOne(): void
    {
        $definition = new QuantityPriceDefinition(9.99, new TaxRuleCollection());

        static::assertSame(1, $definition->getQuantity());
    }

    public function testPriceIsCastOnConstruction(): void
    {
        $definition = new QuantityPriceDefinition(0.1 + 0.2, new TaxRuleCollection());

        static::assertSame(0.3, $definition->getPrice());
    }

    public function testTypePriorityAndApiAlias(): void
    {
        $definition = new QuantityPriceDefinition(9.99, new TaxRuleCollection());

        static::assertSame('quantity', $definition->getType());
        static::assertSame(100, $definition->getPriority());
        static::assertSame('cart_price_quantity', $definition->getApiAlias());
    }

    public function testFromArrayWithAllValues(): void
    {
        $definition = QuantityPriceDefinition::fromArray([
            'price' => 19.99,
            'taxRules' => [
                ['taxRate' => 19.0, 'percentage' => 100.0],
                ['taxRate' => 7.0, 'percentage' => 50.0],
            ],
            'quantity' => 5,
            'isCalculated' => true,
            'listPrice' => 24.99,
            'regulationPrice' => 29.99,
        ]);

        static::assertSame(19.99, $definition->getPrice());
        static::assertSame(5, $definition->getQuantity());
        static::assertTrue($definition->isCalculated());
        static::assertSame(24.99, $definition->getListPrice());
        static::assertSame(29.99, $definition->getRegulationPrice());

        $taxRules = array_values($definition->getTaxRules()->getElements());
        static::assertCount(2, $taxRules);

        static::assertSame(19.0, $taxRules[0]->getTaxRate());
        static::assertSame(100.0, $taxRules[0]->getPercentage());

        static::assertSame(7.0, $taxRules[1]->getTaxRate());
        static::assertSame(50.0, $taxRules[1]->getPercentage());
    }

    public function testFromArrayAppliesDefaultsForOptionalKeys(): void
    {
        $definition = QuantityPriceDefinition::fromArray([
            'price' => 19.99,
            'taxRules' => [
                ['taxRate' => 19.0, 'percentage' => 100.0],
            ],
        ]);

        static::assertSame(19.99, $definition->getPrice());
        static::assertSame(1, $definition->getQuantity());
        static::assertFalse($definition->isCalculated());
        static::assertNull($definition->getListPrice());
        static::assertNull($definition->getRegulationPrice());
        static::assertCount(1, $definition->getTaxRules());
    }

    public function testListPriceIsNullByDefault(): void
    {
        $definition = new QuantityPriceDefinition(9.99, new TaxRuleCollection());

        static::assertNull($definition->getListPrice());
    }

    #[DataProvider('optionalPriceProvider')]
    #[TestDox('setListPrice/setRegulationPrice cast and null out with input $input')]
    public function testListAndRegulationPriceCasting(?float $input, ?float $expected): void
    {
        $definition = new QuantityPriceDefinition(9.99, new TaxRuleCollection());

        $definition->setListPrice($input);
        $definition->setRegulationPrice($input);

        static::assertSame($expected, $definition->getListPrice());
        static::assertSame($expected, $definition->getRegulationPrice());
    }

    public static function optionalPriceProvider(): \Generator
    {
        yield 'null stays null' => [null, null];
        yield 'zero returns null' => [0.0, null];
        yield 'float artifact is normalized' => [0.1 + 0.2, 0.3];
        yield 'plain value kept' => [14.99, 14.99];
    }

    public function testJsonSerializeInjectsType(): void
    {
        $definition = new QuantityPriceDefinition(9.99, new TaxRuleCollection(), 2);

        $data = $definition->jsonSerialize();

        static::assertArrayHasKey('type', $data);
        static::assertSame('quantity', $data['type']);
    }

    public function testGetConstraintsIsKeyedByField(): void
    {
        $constraints = QuantityPriceDefinition::getConstraints();

        static::assertArrayHasKey('price', $constraints);
        static::assertArrayHasKey('quantity', $constraints);
        static::assertArrayHasKey('isCalculated', $constraints);
    }
}
