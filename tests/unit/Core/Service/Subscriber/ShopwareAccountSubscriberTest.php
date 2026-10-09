<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLoginEvent;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLogoutEvent;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\Subscriber\ShopwareAccountSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwareAccountSubscriber::class)]
class ShopwareAccountSubscriberTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testReevaluatesServicesOnLogin(): void
    {
        $event = new ShopwareAccountLoginEvent($this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        (new ShopwareAccountSubscriber($serviceLifecycle))->reevaluateServices($event);
    }

    public function testReevaluatesServicesOnLogout(): void
    {
        $event = new ShopwareAccountLogoutEvent($this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        (new ShopwareAccountSubscriber($serviceLifecycle))->reevaluateServices($event);
    }

    public function testSubscribedEvents(): void
    {
        $events = ShopwareAccountSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(ShopwareAccountLoginEvent::class, $events);
        static::assertArrayHasKey(ShopwareAccountLogoutEvent::class, $events);
        static::assertSame('reevaluateServices', $events[ShopwareAccountLoginEvent::class]);
        static::assertSame('reevaluateServices', $events[ShopwareAccountLogoutEvent::class]);
    }
}
