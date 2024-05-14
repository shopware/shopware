<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\SalesChannelContextAssembledEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelContextAssembledEvent::class)]
class SalesChannelContextAssembledEventTest extends TestCase
{
    public function testConstruct(): void
    {
        $order = new OrderEntity();
        $context = Generator::createSalesChannelContext();

        $event = new SalesChannelContextAssembledEvent($order, $context);

        static::assertSame($order, $event->getOrder());
        static::assertSame($context->getContext(), $event->getContext());
    }
}
