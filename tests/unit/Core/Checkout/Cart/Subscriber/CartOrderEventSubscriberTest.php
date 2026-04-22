<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\Subscriber\CartOrderEventSubscriber;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartOrderEventSubscriber::class)]
class CartOrderEventSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = CartOrderEventSubscriber::getSubscribedEvents();

        static::assertEquals('resetBuilder', $events[BeforeLineItemAddedEvent::class]);
        static::assertEquals('resetBuilder', $events[BeforeLineItemRemovedEvent::class]);
    }

    public function testResetBuilder(): void
    {
        $builder = $this->createMock(LineItemGroupBuilder::class);
        $builder
            ->expects($this->once())
            ->method('reset');

        (new CartOrderEventSubscriber($builder))
            ->resetBuilder($this->createMock(BeforeLineItemAddedEvent::class));
    }
}
