<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Hook\CartHook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(CartHook::class)]
class CartHookTest extends TestCase
{
    public function testNameRespectsCartSource(): void
    {
        $cart = new Cart('test');
        $cart->setSource('test');
        $hook = new CartHook($cart, $this->createMock(SalesChannelContext::class));

        static::assertEquals('cart-test', $hook->getName());
    }

    public function testNameWithoutCartSource(): void
    {
        $cart = new Cart('test');
        $hook = new CartHook($cart, $this->createMock(SalesChannelContext::class));

        static::assertEquals('cart', $hook->getName());
    }
}
