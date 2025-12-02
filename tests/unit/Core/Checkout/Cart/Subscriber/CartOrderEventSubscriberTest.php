<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Subscriber\CartOrderEventSubscriber;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartOrderEventSubscriber::class)]
class CartOrderEventSubscriberTest extends TestCase
{
    private AbstractContextSwitchRoute&MockObject $contextSwitchRoute;

    private CartOrderEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $this->subscriber = new CartOrderEventSubscriber($this->contextSwitchRoute);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CartOrderEventSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(CartDeletedEvent::class, $events);
        static::assertArrayHasKey(CheckoutOrderPlacedEvent::class, $events);
        static::assertEquals(['handleContextAddress', 1], $events[CartDeletedEvent::class]);
        static::assertEquals(['handleContextAddress', 1], $events[CheckoutOrderPlacedEvent::class]);
    }

    public function testHandleContextAddressWithCartDeletedEvent(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $event = new CartDeletedEvent($salesChannelContext);

        $expectedDataBag = new RequestDataBag([
            SalesChannelContextService::SHIPPING_ADDRESS_ID => null,
            SalesChannelContextService::BILLING_ADDRESS_ID => null,
        ]);

        $this->contextSwitchRoute->expects($this->once())
            ->method('switchContext')
            ->with(
                static::callback(function (RequestDataBag $dataBag) use ($expectedDataBag) {
                    return $dataBag->all() === $expectedDataBag->all();
                }),
                static::equalTo($salesChannelContext)
            );

        $this->subscriber->handleContextAddress($event);
    }

    public function testHandleContextAddressWithCheckoutOrderPlacedEvent(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $orderEntity = new OrderEntity();
        $event = new CheckoutOrderPlacedEvent($salesChannelContext, $orderEntity);

        $expectedDataBag = new RequestDataBag([
            SalesChannelContextService::SHIPPING_ADDRESS_ID => null,
            SalesChannelContextService::BILLING_ADDRESS_ID => null,
        ]);

        $this->contextSwitchRoute->expects($this->once())
            ->method('switchContext')
            ->with(
                static::callback(function (RequestDataBag $dataBag) use ($expectedDataBag) {
                    return $dataBag->all() === $expectedDataBag->all();
                }),
                static::equalTo($salesChannelContext)
            );

        $this->subscriber->handleContextAddress($event);
    }
}
