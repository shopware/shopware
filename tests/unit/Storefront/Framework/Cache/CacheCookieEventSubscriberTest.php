<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\Extension\CacheHashRequiredExtension;
use Shopware\Core\Framework\Adapter\Session\SessionFactory;
use Shopware\Core\Framework\Adapter\Session\StatefulFlashBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\CacheCookieEventSubscriber;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CacheCookieEventSubscriber::class)]
class CacheCookieEventSubscriberTest extends TestCase
{
    private SessionFactory&MockObject $sessionFactoryMock;

    private CacheCookieEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->sessionFactoryMock = $this->createMock(SessionFactory::class);

        $this->subscriber = new CacheCookieEventSubscriber($this->sessionFactoryMock);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                HttpCacheCookieEvent::class => 'passCacheForFlashMessages',
                CacheHashRequiredExtension::NAME . '.post' => 'onRequireCacheHash',
            ],
            CacheCookieEventSubscriber::getSubscribedEvents()
        );
    }

    public function testCacheHashNotRequiredWhenNoFlashMessagesArePresent(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(false);

        $this->sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new CacheHashRequiredExtension(
            new Request(),
            $this->createMock(SalesChannelContext::class),
            new Cart('test')
        );
        $event->result = false;

        $this->subscriber->onRequireCacheHash($event);

        static::assertFalse($event->result);
    }

    public function testCacheHashIsRequiredWhenFlashMessagesArePresent(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(true);

        $this->sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new CacheHashRequiredExtension(
            new Request(),
            $this->createMock(SalesChannelContext::class),
            new Cart('test')
        );
        $event->result = false;

        $this->subscriber->onRequireCacheHash($event);

        static::assertTrue($event->result);
    }

    public function testCacheIsUsedWhenNoFlashMessagesArePresent(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(false);
        $flashBagMock->expects($this->once())
            ->method('displayedAnyFlashes')
            ->willReturn(false);

        $this->sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new HttpCacheCookieEvent(
            new Request(),
            $this->createMock(SalesChannelContext::class),
            []
        );

        $this->subscriber->passCacheForFlashMessages($event);

        static::assertTrue($event->isCacheable);
        static::assertFalse($event->doNotStore);
    }

    public function testCacheIsUsedWhenNoFlashBagIsAvailable(): void
    {
        $this->sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn(null);

        $event = new HttpCacheCookieEvent(
            new Request(),
            $this->createMock(SalesChannelContext::class),
            []
        );

        $this->subscriber->passCacheForFlashMessages($event);

        static::assertTrue($event->isCacheable);
        static::assertFalse($event->doNotStore);
    }

    public function testCacheIsPassedWhenFlashesArePresentOnCacheCookieEvent(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(true);
        $flashBagMock->expects($this->never())
            ->method('displayedAnyFlashes');

        $this->sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new HttpCacheCookieEvent(
            new Request(),
            $this->createMock(SalesChannelContext::class),
            []
        );

        $this->subscriber->passCacheForFlashMessages($event);

        // when flashes are present, we can't use the cache for the next requests, until the flashes are displayed
        static::assertFalse($event->isCacheable);
        static::assertFalse($event->doNotStore);
    }

    public function testCacheIsNotStoredWhenFlashesAreDisplayedDuringRequest(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(false);
        $flashBagMock->expects($this->once())
            ->method('displayedAnyFlashes')
            ->willReturn(true);

        $this->sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new HttpCacheCookieEvent(
            new Request(),
            $this->createMock(SalesChannelContext::class),
            []
        );

        $this->subscriber->passCacheForFlashMessages($event);

        static::assertTrue($event->isCacheable);
        // the current request should not be stored, but all further requests can use the cache
        static::assertTrue($event->doNotStore);
    }
}
