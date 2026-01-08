<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Framework\Adapter\Cache\Http\AdministrationCacheControlListener;
use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdministrationCacheControlListener::class)]
class AdministrationCacheControlListenerTest extends TestCase
{
    public function testInvokeSkipsCacheControlForAdministrationRequestWithCacheIdHeader(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $response = new Response();
        $response->headers->set(AdministrationController::CACHE_ID_HEADER, AdministrationController::CACHE_ID_ADMINISTRATION);

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertTrue($event->shouldSkipCacheControl());
    }

    public function testInvokeSkipsCacheControlForAdministrationRequestWithRouteScope(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [AdministrationRouteScope::ID]);
        $response = new Response();

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertTrue($event->shouldSkipCacheControl());
    }

    public function testInvokeSkipsCacheControlForAdministrationRequestWithRouteName(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $request->attributes->set('_route', 'administration.index');
        $response = new Response();

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertTrue($event->shouldSkipCacheControl());
    }

    public function testInvokeSkipsCacheControlForAdministrationRequestWithRouteNamePrefix(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $request->attributes->set('_route', 'administration.plugin.index');
        $response = new Response();

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertTrue($event->shouldSkipCacheControl());
    }

    public function testInvokeDoesNotSkipCacheControlForNonAdministrationRequest(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $response = new Response();

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertFalse($event->shouldSkipCacheControl());
    }

    public function testInvokeDoesNotSkipCacheControlForNonAdministrationRouteScope(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['some-other-scope']);
        $response = new Response();

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertFalse($event->shouldSkipCacheControl());
    }

    public function testInvokeDoesNotSkipCacheControlForNonAdministrationRouteName(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $request->attributes->set('_route', 'storefront.page');
        $response = new Response();

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertFalse($event->shouldSkipCacheControl());
    }

    public function testInvokeWithWrongCacheIdHeaderValue(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $response = new Response();
        $response->headers->set(AdministrationController::CACHE_ID_HEADER, 'different-value');

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertFalse($event->shouldSkipCacheControl());
    }

    public function testInvokeWithNonStringRouteName(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $request->attributes->set('_route', 123); // Non-string route name
        $response = new Response();

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertFalse($event->shouldSkipCacheControl());
    }

    public function testInvokeWithEmptyRouteScopeArray(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);
        $response = new Response();

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertFalse($event->shouldSkipCacheControl());
    }

    public function testInvokeSkipsCacheControlWhenMultipleMarkersPresent(): void
    {
        $listener = new AdministrationCacheControlListener();

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [AdministrationRouteScope::ID]);
        $request->attributes->set('_route', 'administration.index');
        $response = new Response();
        $response->headers->set(AdministrationController::CACHE_ID_HEADER, AdministrationController::CACHE_ID_ADMINISTRATION);

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertTrue($event->shouldSkipCacheControl());
    }
}
