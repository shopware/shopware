<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLoginEvent;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLogoutEvent;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Requirement\ShopwareAccountRequirement;
use Shopware\Core\Service\Subscriber\ShopwareAccountSubscriber;

/**
 * @internal
 */
#[CoversClass(ShopwareAccountSubscriber::class)]
class ShopwareAccountSubscriberTest extends TestCase
{
    private LifecycleManager&MockObject $manager;

    private ShopwareAccountSubscriber $subscriber;

    private Context $context;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(LifecycleManager::class);
        $this->subscriber = new ShopwareAccountSubscriber($this->manager);
        $this->context = Context::createDefaultContext();
    }

    public function testSyncAccountRequirementOnLogin(): void
    {
        $event = new ShopwareAccountLoginEvent($this->context);

        $this->manager
            ->expects($this->once())
            ->method('syncRequirement')
            ->with(ShopwareAccountRequirement::NAME, $this->context);

        $this->subscriber->syncAccountRequirement($event);
    }

    public function testSyncAccountRequirementOnLogout(): void
    {
        $event = new ShopwareAccountLogoutEvent($this->context);

        $this->manager
            ->expects($this->once())
            ->method('syncRequirement')
            ->with(ShopwareAccountRequirement::NAME, $this->context);

        $this->subscriber->syncAccountRequirement($event);
    }

    public function testSubscribedEvents(): void
    {
        $events = ShopwareAccountSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(ShopwareAccountLoginEvent::class, $events);
        static::assertArrayHasKey(ShopwareAccountLogoutEvent::class, $events);
        static::assertSame('syncAccountRequirement', $events[ShopwareAccountLoginEvent::class]);
        static::assertSame('syncAccountRequirement', $events[ShopwareAccountLogoutEvent::class]);
    }
}
