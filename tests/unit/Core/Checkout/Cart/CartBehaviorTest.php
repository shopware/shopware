<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\CheckoutPermissions;

/**
 * @internal
 */
#[CoversClass(CartBehavior::class)]
class CartBehaviorTest extends TestCase
{
    public function testHasPermission(): void
    {
        $cartBehavior = new CartBehavior([CheckoutPermissions::ALLOW_PRODUCT_LABEL_OVERWRITES => true]);
        static::assertTrue($cartBehavior->hasPermission(CheckoutPermissions::ALLOW_PRODUCT_LABEL_OVERWRITES));
    }

    public function testHasNoPermission(): void
    {
        $cartBehavior = new CartBehavior([CheckoutPermissions::ALLOW_PRODUCT_LABEL_OVERWRITES => true]);
        static::assertFalse($cartBehavior->hasPermission(CheckoutPermissions::ALLOW_PRODUCT_PRICE_OVERWRITES));
    }

    public function testHasNoPermissionWithNoPermissionsSet(): void
    {
        $cartBehavior = new CartBehavior();
        static::assertFalse($cartBehavior->hasPermission(CheckoutPermissions::ALLOW_PRODUCT_LABEL_OVERWRITES));
    }
}
