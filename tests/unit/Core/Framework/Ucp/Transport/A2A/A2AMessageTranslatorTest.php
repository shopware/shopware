<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Transport\A2A;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Transport\A2A\A2AIntent;
use Shopware\Core\Framework\Ucp\Transport\A2A\A2AMessageTranslator;

/**
 * @internal
 */
#[CoversClass(A2AMessageTranslator::class)]
#[CoversClass(A2AIntent::class)]
class A2AMessageTranslatorTest extends TestCase
{
    public function testAddToCartMapsToExistingUpdateCartTool(): void
    {
        $intent = (new A2AMessageTranslator())->translate([
            'parts' => [[
                'type' => 'data',
                'data' => [
                    'action' => 'add_to_cart',
                    'cart_id' => 'cart-1',
                    'product_id' => 'product-1',
                    'quantity' => 2,
                ],
            ]],
        ]);

        static::assertInstanceOf(A2AIntent::class, $intent);
        static::assertSame('update_cart', $intent->toolName);
        static::assertSame('cart', $intent->resource);
        static::assertSame('cart-1', $intent->arguments['id']);
        static::assertSame(2, $intent->arguments['line_items'][0]['quantity']);
    }

    public function testRemoveFromCartMapsToUpdateCartQuantityZero(): void
    {
        $intent = (new A2AMessageTranslator())->translate([
            'parts' => [[
                'type' => 'data',
                'data' => [
                    'action' => 'remove_from_cart',
                    'cart_id' => 'cart-1',
                    'product_id' => 'product-1',
                ],
            ]],
        ]);

        static::assertInstanceOf(A2AIntent::class, $intent);
        static::assertSame('update_cart', $intent->toolName);
        static::assertSame(0, $intent->arguments['line_items'][0]['quantity']);
    }
}
