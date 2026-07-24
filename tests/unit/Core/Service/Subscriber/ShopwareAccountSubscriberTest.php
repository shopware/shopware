<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLoginEvent;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLogoutEvent;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Requirement\ShopwareAccountRequirement;
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

    public function testSyncAccountRequirementOnLogin(): void
    {
        $event = new ShopwareAccountLoginEvent($this->context);

        $manager = $this->createMock(LifecycleManager::class);
        $manager
            ->expects($this->once())
            ->method('reevaluateRequirement')
            ->with(ShopwareAccountRequirement::NAME, $this->context);

        (new ShopwareAccountSubscriber($manager))->syncAccountRequirement($event);
    }

    public function testSyncAccountRequirementOnLogout(): void
    {
        $event = new ShopwareAccountLogoutEvent($this->context);

        $manager = $this->createMock(LifecycleManager::class);
        $manager
            ->expects($this->once())
            ->method('reevaluateRequirement')
            ->with(ShopwareAccountRequirement::NAME, $this->context);

        (new ShopwareAccountSubscriber($manager))->syncAccountRequirement($event);
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
