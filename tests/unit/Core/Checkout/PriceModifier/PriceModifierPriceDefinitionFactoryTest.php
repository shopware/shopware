<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\PriceModifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\PriceModifier\PriceModifierAbsolutePriceDefinition;
use Shopware\Core\Checkout\PriceModifier\PriceModifierPercentagePriceDefinition;
use Shopware\Core\Checkout\PriceModifier\PriceModifierPriceDefinitionFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceModifierPriceDefinitionFactory::class)]
class PriceModifierPriceDefinitionFactoryTest extends TestCase
{
    public function testReturnsNullForMissingData(): void
    {
        static::assertNull(PriceModifierPriceDefinitionFactory::fromArray(null));
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('unsupportedTypeProvider')]
    public function testReturnsNullForUnsupportedType(array $data): void
    {
        static::assertNull(PriceModifierPriceDefinitionFactory::fromArray($data));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unsupportedTypeProvider(): iterable
    {
        yield 'type is missing' => [['price' => 10.0]];
        yield 'type is unknown, e.g. from an uninstalled plugin' => [['type' => 'plugin-specific', 'price' => 10.0]];
    }

    public function testHydratesAbsoluteDefinition(): void
    {
        $definition = PriceModifierPriceDefinitionFactory::fromArray([
            'type' => PriceModifierAbsolutePriceDefinition::TYPE,
            'price' => '-50.5',
            'taxRules' => [
                ['taxRate' => 19, 'percentage' => 60],
                ['taxRate' => '7'],
            ],
            'taxExempt' => 1,
            'filter' => ['type' => 'product'],
        ]);

        static::assertInstanceOf(PriceModifierAbsolutePriceDefinition::class, $definition);
        static::assertSame(-50.5, $definition->getPrice());
        static::assertEquals(
            new TaxRuleCollection([new TaxRule(19.0, 60.0), new TaxRule(7.0, 100.0)]),
            $definition->getTaxRules()
        );
        static::assertTrue($definition->isTaxExempt());
        static::assertSame(['type' => 'product'], $definition->getFilter());
    }

    public function testAbsoluteDefinitionFallsBackToDefaults(): void
    {
        $definition = PriceModifierPriceDefinitionFactory::fromArray([
            'type' => PriceModifierAbsolutePriceDefinition::TYPE,
            'taxRules' => [],
        ]);

        static::assertInstanceOf(PriceModifierAbsolutePriceDefinition::class, $definition);
        static::assertSame(0.0, $definition->getPrice());
        static::assertNull($definition->getTaxRules(), 'an empty tax rule list means "no fixed tax rules"');
        static::assertFalse($definition->isTaxExempt());
        static::assertNull($definition->getFilter());
    }

    public function testHydratesPercentageDefinition(): void
    {
        $definition = PriceModifierPriceDefinitionFactory::fromArray([
            'type' => PriceModifierPercentagePriceDefinition::TYPE,
            'percentage' => -10,
            'target' => ['lineItems' => true, 'shipping' => false],
            'taxRules' => [['taxRate' => 19.0, 'percentage' => 100.0]],
            'taxExempt' => false,
        ]);

        static::assertInstanceOf(PriceModifierPercentagePriceDefinition::class, $definition);
        static::assertSame(-10.0, $definition->getPercentage());
        static::assertSame(['lineItems' => true, 'shipping' => false], $definition->getTarget());
        static::assertEquals(new TaxRuleCollection([new TaxRule(19.0)]), $definition->getTaxRules());
        static::assertFalse($definition->isTaxExempt());
    }

    public function testPercentageDefinitionTargetsLineItemsAndShippingByDefault(): void
    {
        $definition = PriceModifierPriceDefinitionFactory::fromArray([
            'type' => PriceModifierPercentagePriceDefinition::TYPE,
        ]);

        static::assertInstanceOf(PriceModifierPercentagePriceDefinition::class, $definition);
        static::assertSame(0.0, $definition->getPercentage());
        static::assertSame(['lineItems' => true, 'shipping' => true], $definition->getTarget());
        static::assertNull($definition->getTaxRules());
    }

    public function testRoundTripsJsonSerializedDefinition(): void
    {
        $original = new PriceModifierPercentagePriceDefinition(
            percentage: -15.0,
            target: ['lineItems' => false, 'shipping' => true],
            taxRules: new TaxRuleCollection([new TaxRule(19.0)]),
            taxExempt: true,
        );

        /** @var array<string, mixed> $data */
        $data = json_decode((string) json_encode($original, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals($original, PriceModifierPriceDefinitionFactory::fromArray($data));
    }
}
