<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\RegulationPrice;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RegulationPrice::class)]
class RegulationPriceTest extends TestCase
{
    public function testCreateFromUnitPriceCalculatesTheSaving(): void
    {
        $regulationPrice = RegulationPrice::createFromUnitPrice(75, 80);

        static::assertSame(80.0, $regulationPrice->getPrice());
        static::assertSame(-5.0, $regulationPrice->getDiscount());
        static::assertSame(6.25, $regulationPrice->getPercentage());
    }

    public function testCreateFromUnitPriceWithoutSaving(): void
    {
        $regulationPrice = RegulationPrice::createFromUnitPrice(80, 80);

        static::assertSame(80.0, $regulationPrice->getPrice());
        static::assertSame(0.0, $regulationPrice->getDiscount());
        static::assertSame(0.0, $regulationPrice->getPercentage());
    }

    public function testConstructorDefaultsToNoSaving(): void
    {
        $regulationPrice = new RegulationPrice(80);

        static::assertSame(80.0, $regulationPrice->getPrice());
        static::assertSame(0.0, $regulationPrice->getDiscount());
        static::assertSame(0.0, $regulationPrice->getPercentage());
    }

    public function testGetApiAlias(): void
    {
        static::assertSame('cart_regulation_price', (new RegulationPrice(80))->getApiAlias());
    }
}
