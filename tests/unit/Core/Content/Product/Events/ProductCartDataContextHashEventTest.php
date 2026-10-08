<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Content\Product\Events\ProductCartDataContextHashEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductCartDataContextHashEvent::class)]
class ProductCartDataContextHashEventTest extends TestCase
{
    public function testExposesTheContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $data = new CartDataCollection();
        $cart = new Cart('test');
        $behavior = new CartBehavior();

        $event = new ProductCartDataContextHashEvent($data, $cart, $salesChannelContext, $behavior);

        static::assertSame($data, $event->getData());
        static::assertSame($cart, $event->getOriginalCart());
        static::assertSame($behavior, $event->getBehavior());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $event->getContext());
        static::assertSame([], $event->getParts());
    }

    public function testPartsAreSortedByNameAndOverwritten(): void
    {
        $event = new ProductCartDataContextHashEvent(new CartDataCollection(), new Cart('test'), Generator::generateSalesChannelContext(), new CartBehavior());

        $event->add('b', 'second');
        $event->add('a', ['first']);
        $event->add('b', 2);

        static::assertSame(['a' => ['first'], 'b' => 2], $event->getParts());
    }
}
