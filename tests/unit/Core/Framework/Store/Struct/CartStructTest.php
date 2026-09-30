<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\CartPositionCollection;
use Shopware\Core\Framework\Store\Struct\CartPositionStruct;
use Shopware\Core\Framework\Store\Struct\CartStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartStruct::class)]
class CartStructTest extends TestCase
{
    public function testFromArrayWrapsThePositionsInACollection(): void
    {
        $cart = CartStruct::fromArray([
            'netPrice' => 10.0,
            'positions' => [
                ['netPrice' => 10.0, 'grossPrice' => 11.9],
            ],
        ]);

        static::assertSame(10.0, $cart->getNetPrice());
        static::assertCount(1, $cart->getPositions());
        $position = $cart->getPositions()->first();
        static::assertInstanceOf(CartPositionStruct::class, $position);
        static::assertSame(11.9, $position->getGrossPrice());
    }

    public function testJsonSerializeOmitsTheExtensions(): void
    {
        $cart = CartStruct::fromArray(['netPrice' => 10.0, 'positions' => []]);

        $data = $cart->jsonSerialize();

        static::assertArrayNotHasKey('extensions', $data);
        static::assertSame(10.0, $data['netPrice']);
    }

    public function testAccessorsRoundTrip(): void
    {
        $cart = new CartStruct();

        $positions = new CartPositionCollection();

        $cart->setNetPrice(10.0);
        $cart->setTaxValue(1.9);
        $cart->setTaxRate(19.0);
        $cart->setGrossPrice(11.9);
        $cart->setPositions($positions);
        $cart->setShop(['id' => 7, 'domain' => 'example.com']);

        static::assertSame(10.0, $cart->getNetPrice());
        static::assertSame(1.9, $cart->getTaxValue());
        static::assertSame(19.0, $cart->getTaxRate());
        static::assertSame(11.9, $cart->getGrossPrice());
        static::assertSame($positions, $cart->getPositions());
        static::assertSame(['id' => 7, 'domain' => 'example.com'], $cart->getShop());
        static::assertSame(7, $cart->getShopId());
        static::assertSame('example.com', $cart->getShopDomain());
    }
}
