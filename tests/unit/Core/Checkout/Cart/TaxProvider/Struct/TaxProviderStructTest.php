<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;

/**
 * @internal
 */
#[CoversClass(TaxProviderResult::class)]
class TaxProviderStructTest extends TestCase
{
    public function testEmpty(): void
    {
        $struct = new TaxProviderResult();

        static::assertNull($struct->getLineItemTaxes());
        static::assertNull($struct->getDeliveryTaxes());
        static::assertNull($struct->getCartPriceTaxes());
        static::assertFalse($struct->declaresTaxes());
    }

    /**
     * @param array<string, CalculatedTaxCollection>|null $lineItemTaxes
     * @param array<string, CalculatedTaxCollection>|null $deliveryTaxes
     */
    #[DataProvider('structDataProvider')]
    public function testDirty(
        ?array $lineItemTaxes,
        ?array $deliveryTaxes,
        ?CalculatedTaxCollection $cartPriceTaxes,
        bool $dirty
    ): void {
        $struct = (new TaxProviderResult())
            ->assign(['lineItemTaxes' => $lineItemTaxes])
            ->assign(['deliveryTaxes' => $deliveryTaxes])
            ->assign(['cartPriceTaxes' => $cartPriceTaxes]);

        static::assertSame($dirty, $struct->declaresTaxes());
    }

    /**
     * @return \Generator
     */
    public static function structDataProvider(): iterable
    {
        yield [null, null, null, false];
        yield [[], null, null, false];
        yield [null, [], null, false];
        yield [null, null, new CalculatedTaxCollection(), false];

        yield [['foo' => new CalculatedTaxCollection()], null, null, true];
        yield [null, ['foo' => new CalculatedTaxCollection()], null, true];
        yield [null, null, new CalculatedTaxCollection([new CalculatedTax(0, 0, 0)]), true];
    }
}
