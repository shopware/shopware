<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\PriceModifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\PriceModifier\PriceModifier;
use Shopware\Core\Checkout\PriceModifier\PriceModifierAbsolutePriceDefinition;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCalculatedPrice;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceModifier::class)]
class PriceModifierTest extends TestCase
{
    public function testExposesAllConstructorValues(): void
    {
        $price = new PriceModifierCalculatedPrice(-50.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $definition = new PriceModifierAbsolutePriceDefinition(-50.0);

        $modifier = new PriceModifier(
            label: 'Voucher',
            price: $price,
            priceDefinition: $definition,
            id: 'modifier-id',
            type: 'voucher',
            referencedId: 'voucher-id',
            description: 'Welcome voucher',
            payload: ['code' => 'WELCOME50'],
        );

        static::assertSame('Voucher', $modifier->getLabel());
        static::assertSame($price, $modifier->getPrice());
        static::assertSame($definition, $modifier->getPriceDefinition());
        static::assertSame('modifier-id', $modifier->getId());
        static::assertSame('voucher', $modifier->getType());
        static::assertSame('voucher-id', $modifier->getReferencedId());
        static::assertSame('Welcome voucher', $modifier->getDescription());
        static::assertSame(['code' => 'WELCOME50'], $modifier->getPayload());
        static::assertSame('cart_price_modifier', $modifier->getApiAlias());
    }

    public function testOptionalValuesDefaultToNull(): void
    {
        $modifier = new PriceModifier(
            'Rush order fee',
            new PriceModifierCalculatedPrice(15.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new PriceModifierAbsolutePriceDefinition(15.0),
        );

        static::assertNull($modifier->getId());
        static::assertNull($modifier->getType());
        static::assertNull($modifier->getReferencedId());
        static::assertNull($modifier->getDescription());
        static::assertNull($modifier->getPayload());
    }

    public function testWithPriceReturnsCopyWithOnlyThePriceReplaced(): void
    {
        $modifier = new PriceModifier(
            label: 'Voucher',
            price: new PriceModifierCalculatedPrice(-80.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            priceDefinition: new PriceModifierAbsolutePriceDefinition(-80.0, taxExempt: true),
            id: 'modifier-id',
            payload: ['code' => 'GIFT'],
        );
        $trimmedPrice = new PriceModifierCalculatedPrice(-20.0, new CalculatedTaxCollection(), new TaxRuleCollection());

        $trimmed = $modifier->withPrice($trimmedPrice);

        static::assertNotSame($modifier, $trimmed);
        static::assertSame($trimmedPrice, $trimmed->getPrice());
        static::assertSame(-80.0, $modifier->getPrice()->getTotalPrice());
        static::assertSame('modifier-id', $trimmed->getId());
        static::assertSame(['code' => 'GIFT'], $trimmed->getPayload());
        static::assertEquals($modifier->getPriceDefinition(), $trimmed->getPriceDefinition());
    }
}
