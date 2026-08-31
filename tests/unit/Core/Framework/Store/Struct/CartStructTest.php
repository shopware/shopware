<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
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
}
