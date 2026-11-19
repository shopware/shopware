<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\SelectedPrice;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SelectedPrice::class)]
class SelectedPriceTest extends TestCase
{
    public function testCarriesTheSelectedValueAsAlreadyCalculated(): void
    {
        $price = new SelectedPrice(11.9, isCalculated: true);

        static::assertSame(11.9, $price->getValue());
        static::assertTrue($price->isCalculated());
    }

    public function testCarriesTheSelectedValueAsStillToBeDerived(): void
    {
        $price = new SelectedPrice(10.0, isCalculated: false);

        static::assertSame(10.0, $price->getValue());
        static::assertFalse($price->isCalculated());
    }

    public function testSerializesBothProperties(): void
    {
        $price = new SelectedPrice(10.0, isCalculated: false);

        static::assertSame(
            ['extensions' => [], 'value' => 10.0, 'isCalculated' => false],
            $price->jsonSerialize()
        );
    }
}
