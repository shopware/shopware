<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\CartBeforeSerializationEvent;

/**
 * @internal
 */
#[CoversClass(CartBeforeSerializationEvent::class)]
class CartBeforeSerializationEventTest extends TestCase
{
    public function testConstructor(): void
    {
        $cart = new Cart('cart');
        $customFieldAllowList = ['foo', 'bar'];

        $event = new CartBeforeSerializationEvent($cart, $customFieldAllowList);

        static::assertSame($cart, $event->getCart());
        static::assertSame($customFieldAllowList, $event->getCustomFieldAllowList());
    }

    public function testSetCustomFieldAllowList(): void
    {
        $customFieldAllowList = ['foo', 'bar'];

        $event = new CartBeforeSerializationEvent(new Cart('cart'), $customFieldAllowList);
        $event->setCustomFieldAllowList(['boo']);

        static::assertSame(['boo'], $event->getCustomFieldAllowList());
    }

    public function testAddCustomFieldAllowList(): void
    {
        $customFieldAllowList = ['foo', 'bar'];

        $event = new CartBeforeSerializationEvent(new Cart('cart'), $customFieldAllowList);
        $event->addCustomFieldToAllowList('boo');

        static::assertSame(['foo', 'bar', 'boo'], $event->getCustomFieldAllowList());
    }
}
