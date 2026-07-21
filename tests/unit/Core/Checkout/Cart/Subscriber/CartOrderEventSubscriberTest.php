<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
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
    private AbstractContextSwitchRoute&Stub $contextSwitchRoute;

    protected function setUp(): void
    {
        $this->contextSwitchRoute = static::createStub(AbstractContextSwitchRoute::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CartOrderEventSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(CartDeletedEvent::class, $events);
        static::assertArrayHasKey(CheckoutOrderPlacedEvent::class, $events);
        static::assertEquals(['handleContextAddress', 1], $events[CartDeletedEvent::class]);
        static::assertEquals(['handleContextAddress', 1], $events[CheckoutOrderPlacedEvent::class]);
        static::assertEquals('resetBuilder', $events[BeforeLineItemAddedEvent::class]);
        static::assertEquals('resetBuilder', $events[BeforeLineItemRemovedEvent::class]);
    }

    public function testHandleContextAddressWithCartDeletedEvent(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $event = new CartDeletedEvent($salesChannelContext);

        $expectedDataBag = new RequestDataBag([
            SalesChannelContextService::SHIPPING_ADDRESS_ID => null,
            SalesChannelContextService::BILLING_ADDRESS_ID => null,
        ]);

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())
            ->method('switchContext')
            ->with(
                static::callback(static function (RequestDataBag $dataBag) use ($expectedDataBag) {
                    return $dataBag->all() === $expectedDataBag->all();
                }),
                static::equalTo($salesChannelContext)
            );

        $this->buildSubscriber($contextSwitchRoute)->handleContextAddress($event);
    }

    public function testHandleContextAddressWithCheckoutOrderPlacedEvent(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $orderEntity = new OrderEntity();
        $event = new CheckoutOrderPlacedEvent($salesChannelContext, $orderEntity);

        $expectedDataBag = new RequestDataBag([
            SalesChannelContextService::SHIPPING_ADDRESS_ID => null,
            SalesChannelContextService::BILLING_ADDRESS_ID => null,
        ]);

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())
            ->method('switchContext')
            ->with(
                static::callback(static function (RequestDataBag $dataBag) use ($expectedDataBag) {
                    return $dataBag->all() === $expectedDataBag->all();
                }),
                static::equalTo($salesChannelContext)
            );

        $this->buildSubscriber($contextSwitchRoute)->handleContextAddress($event);
    }

    public function testResetBuilder(): void
    {
        $builder = $this->createMock(LineItemGroupBuilder::class);
        $builder
            ->expects($this->once())
            ->method('reset');

        (new CartOrderEventSubscriber(static::createStub(AbstractContextSwitchRoute::class), $builder))
            ->resetBuilder(static::createStub(BeforeLineItemAddedEvent::class));
    }

    private function buildSubscriber(?AbstractContextSwitchRoute $contextSwitchRoute = null): CartOrderEventSubscriber
    {
        return new CartOrderEventSubscriber(
            $contextSwitchRoute ?? $this->contextSwitchRoute,
            static::createStub(LineItemGroupBuilder::class),
        );
    }
}
