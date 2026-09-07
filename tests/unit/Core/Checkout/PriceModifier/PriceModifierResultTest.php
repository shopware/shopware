<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\PriceModifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCollection;
use Shopware\Core\Checkout\PriceModifier\PriceModifierResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceModifierResult::class)]
class PriceModifierResultTest extends TestCase
{
    public function testOptionalValuesDefaultToNeutralAdjustment(): void
    {
        $prices = new PriceCollection();
        $shippingCosts = new PriceCollection();

        $result = new PriceModifierResult($prices, $shippingCosts);

        static::assertSame($prices, $result->prices);
        static::assertSame($shippingCosts, $result->shippingCosts);
        static::assertCount(0, $result->additionalCosts);
        static::assertSame(0.0, $result->taxExemptAdjustment);
        static::assertCount(0, $result->modifiers);
    }

    public function testKeepsGivenValues(): void
    {
        $additionalCosts = new PriceCollection();
        $modifiers = new PriceModifierCollection();

        $result = new PriceModifierResult(
            new PriceCollection(),
            new PriceCollection(),
            additionalCosts: $additionalCosts,
            taxExemptAdjustment: -25.0,
            modifiers: $modifiers,
        );

        static::assertSame($additionalCosts, $result->additionalCosts);
        static::assertSame(-25.0, $result->taxExemptAdjustment);
        static::assertSame($modifiers, $result->modifiers);
    }
}
