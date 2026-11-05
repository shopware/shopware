<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;

/**
 * @internal
 */
#[CoversClass(QuantityPriceDefinition::class)]
class QuantityPriceDefinitionTest extends TestCase
{
    public function testFromArrayRestoresReferencePriceDefinition(): void
    {
        $definition = QuantityPriceDefinition::fromArray([
            'price' => 19.99,
            'taxRules' => [
                ['taxRate' => 19.0, 'percentage' => 100.0],
            ],
            'referencePriceDefinition' => [
                'purchaseUnit' => 0.5,
                'referenceUnit' => 1,
                'unitName' => 'Liter',
                'extensions' => [],
            ],
        ]);

        static::assertEquals(new ReferencePriceDefinition(0.5, 1.0, 'Liter'), $definition->getReferencePriceDefinition());
    }

    #[DataProvider('incompleteReferencePriceDefinitionProvider')]
    public function testFromArrayIgnoresIncompleteReferencePriceDefinition(mixed $referencePriceDefinition): void
    {
        $definition = QuantityPriceDefinition::fromArray([
            'price' => 19.99,
            'taxRules' => [
                ['taxRate' => 19.0, 'percentage' => 100.0],
            ],
            'referencePriceDefinition' => $referencePriceDefinition,
        ]);

        static::assertNull($definition->getReferencePriceDefinition());
    }

    public static function incompleteReferencePriceDefinitionProvider(): \Generator
    {
        yield 'explicit null' => [null];
        yield 'not an array' => ['0.5 Liter'];
        yield 'missing unit name' => [['purchaseUnit' => 0.5, 'referenceUnit' => 1]];
        yield 'missing purchase unit' => [['referenceUnit' => 1, 'unitName' => 'Liter']];
        yield 'missing reference unit' => [['purchaseUnit' => 0.5, 'unitName' => 'Liter']];
    }
}
