<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ListPrice::class)]
class ListPriceTest extends TestCase
{
    public function testCreateFromUnitPriceCalculatesTheSaving(): void
    {
        $listPrice = ListPrice::createFromUnitPrice(75, 100);

        static::assertSame(100.0, $listPrice->getPrice());
        static::assertSame(-25.0, $listPrice->getDiscount());
        static::assertSame(25.0, $listPrice->getPercentage());
    }

    public function testCreateFromUnitPriceWithoutSaving(): void
    {
        $listPrice = ListPrice::createFromUnitPrice(100, 100);

        static::assertSame(100.0, $listPrice->getPrice());
        static::assertSame(0.0, $listPrice->getDiscount());
        static::assertSame(0.0, $listPrice->getPercentage());
    }

    public function testGetApiAlias(): void
    {
        static::assertSame('cart_list_price', ListPrice::createFromUnitPrice(75, 100)->getApiAlias());
    }
}
