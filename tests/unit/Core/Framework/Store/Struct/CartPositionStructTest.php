<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\CartPositionStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartPositionStruct::class)]
class CartPositionStructTest extends TestCase
{
    public function testJsonSerializeOmitsTheExtensions(): void
    {
        $position = CartPositionStruct::fromArray(['netPrice' => 10.0, 'grossPrice' => 11.9]);

        $data = $position->jsonSerialize();

        static::assertArrayNotHasKey('extensions', $data);
        static::assertSame(10.0, $data['netPrice']);
        static::assertSame(11.9, $data['grossPrice']);
    }
}
