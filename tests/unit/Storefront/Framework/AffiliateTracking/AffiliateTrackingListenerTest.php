<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\AffiliateTracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(AffiliateTrackingListener::class)]
class AffiliateTrackingListenerTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            HttpCacheKeyEvent::class => 'disableCacheForAffiliateTracking',
            KernelEvents::CONTROLLER => [
                ['checkAffiliateTracking', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST],
            ],
        ], AffiliateTrackingListener::getSubscribedEvents());
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('trackingParameterProvider')]
    public function testDisablesCacheForAffiliateTracking(array $query): void
    {
        $event = new HttpCacheKeyEvent(new Request(query: $query));

        (new AffiliateTrackingListener())->disableCacheForAffiliateTracking($event);

        static::assertFalse($event->isCacheable);
    }

    public function testDoesNotDisableCacheWithoutAffiliateTracking(): void
    {
        $event = new HttpCacheKeyEvent(new Request(query: ['foo' => 'bar']));

        (new AffiliateTrackingListener())->disableCacheForAffiliateTracking($event);

        static::assertTrue($event->isCacheable);
    }

    public function testStoresAffiliateTrackingInSession(): void
    {
        $request = $this->createStorefrontRequest([
            AffiliateTrackingListener::AFFILIATE_CODE_KEY => 'affiliate-code',
            AffiliateTrackingListener::CAMPAIGN_CODE_KEY => 'campaign-code',
        ]);

        (new AffiliateTrackingListener())->checkAffiliateTracking($this->createControllerEvent($request));

        static::assertSame('affiliate-code', $request->getSession()->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY));
        static::assertSame('campaign-code', $request->getSession()->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY));
        static::assertTrue($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public function testDoesNotStoreAffiliateTrackingOutsideStorefrontScope(): void
    {
        $request = new Request(query: [
            AffiliateTrackingListener::AFFILIATE_CODE_KEY => 'affiliate-code',
        ]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        (new AffiliateTrackingListener())->checkAffiliateTracking($this->createControllerEvent($request));

        static::assertFalse($request->getSession()->has(AffiliateTrackingListener::AFFILIATE_CODE_KEY));
        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public function testDoesNotStoreAffiliateTrackingWithoutAffiliateTracking(): void
    {
        $request = $this->createStorefrontRequest(['foo' => 'bar']);

        (new AffiliateTrackingListener())->checkAffiliateTracking($this->createControllerEvent($request));

        static::assertFalse($request->getSession()->has(AffiliateTrackingListener::AFFILIATE_CODE_KEY));
        static::assertFalse($request->getSession()->has(AffiliateTrackingListener::CAMPAIGN_CODE_KEY));
        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public static function trackingParameterProvider(): \Generator
    {
        yield 'affiliate code' => [[AffiliateTrackingListener::AFFILIATE_CODE_KEY => 'affiliate-code']];
        yield 'campaign code' => [[AffiliateTrackingListener::CAMPAIGN_CODE_KEY => 'campaign-code']];
        yield 'affiliate and campaign code' => [[
            AffiliateTrackingListener::AFFILIATE_CODE_KEY => 'affiliate-code',
            AffiliateTrackingListener::CAMPAIGN_CODE_KEY => 'campaign-code',
        ]];
    }

    /**
     * @param array<string, string> $query
     */
    private function createStorefrontRequest(array $query): Request
    {
        $request = new Request(query: $query);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StorefrontRouteScope::ID]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function createControllerEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
