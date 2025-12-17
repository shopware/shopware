<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\CacheCookieEventSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(CacheCookieEventSubscriber::class)]
class CacheCookieEventSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                KernelEvents::REQUEST => 'onKernelRequest',
                HttpCacheCookieEvent::class => 'passCacheForFlashMessages',
            ],
            CacheCookieEventSubscriber::getSubscribedEvents()
        );
    }

    public function testCacheIsUsedWhenNoRequestIsInStack(): void
    {
        $event = new HttpCacheCookieEvent(
            new Request(),
            $this->createMock(SalesChannelContext::class),
            []
        );

        $subscriber = new CacheCookieEventSubscriber(new RequestStack());

        $subscriber->onKernelRequest();
        $subscriber->passCacheForFlashMessages($event);

        static::assertFalse($event->passCache);
        static::assertFalse($event->doNotStore);
    }

    public function testCacheIsUsedWhenNoFlashMessagesArePresent(): void
    {
        $request = new Request();
        $request->setSession(
            new Session(new MockArraySessionStorage())
        );

        $event = new HttpCacheCookieEvent(
            $request,
            $this->createMock(SalesChannelContext::class),
            []
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $subscriber = new CacheCookieEventSubscriber($requestStack);

        $subscriber->onKernelRequest();
        $subscriber->passCacheForFlashMessages($event);

        static::assertFalse($event->passCache);
        static::assertFalse($event->doNotStore);
    }

    public function testCacheIsPassedWhenFlashesArePresentOnCacheCookieEvent(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new HttpCacheCookieEvent(
            $request,
            $this->createMock(SalesChannelContext::class),
            []
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $subscriber = new CacheCookieEventSubscriber($requestStack);

        $subscriber->onKernelRequest();

        // flashes were added during the request and not displayed
        $session->getFlashBag()->add('warning', 'This is a flash message');

        $subscriber->passCacheForFlashMessages($event);

        // when flashes are present, we can't use the cache for the next requests, until the flashes are displayed
        static::assertTrue($event->passCache);
        static::assertFalse($event->doNotStore);
    }

    public function testCacheIsNotStoredWhenFlashesAreDisplayedDuringRequest(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new HttpCacheCookieEvent(
            $request,
            $this->createMock(SalesChannelContext::class),
            []
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $subscriber = new CacheCookieEventSubscriber($requestStack);

        // we have flashes at the beginning of the request
        $session->getFlashBag()->add('warning', 'This is a flash message');

        $subscriber->onKernelRequest();

        // we clear the flashbag, simulating that the messages were displayed during the request
        $session->getFlashBag()->all();

        $subscriber->passCacheForFlashMessages($event);

        static::assertFalse($event->passCache);
        // the current request should not be stored, but all further requests can use the cache
        static::assertTrue($event->doNotStore);
    }

    public function testResetResetsInternalState(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new HttpCacheCookieEvent(
            $request,
            $this->createMock(SalesChannelContext::class),
            []
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $subscriber = new CacheCookieEventSubscriber($requestStack);

        // we have flashes at the beginning of the request
        $session->getFlashBag()->add('warning', 'This is a flash message');

        $subscriber->onKernelRequest();

        // we clear the flashbag, simulating that the messages were displayed during the request
        $session->getFlashBag()->all();

        $subscriber->passCacheForFlashMessages($event);

        static::assertFalse($event->passCache);
        // the current request should not be stored, but all further requests can use the cache
        static::assertTrue($event->doNotStore);

        $subscriber->reset();

        $event = new HttpCacheCookieEvent(
            $request,
            $this->createMock(SalesChannelContext::class),
            []
        );
        $subscriber->passCacheForFlashMessages($event);

        // after reset the cache should be unaffected
        static::assertFalse($event->passCache);
        static::assertFalse($event->doNotStore);
    }
}
