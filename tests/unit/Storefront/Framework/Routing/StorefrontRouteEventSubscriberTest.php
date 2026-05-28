<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\StorefrontRouteEventSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(StorefrontRouteEventSubscriber::class)]
class StorefrontRouteEventSubscriberTest extends TestCase
{
    #[TestDox('Subscription is gated on v6.8.0.0 — empty without the flag, registers StorefrontRenderEvent with it')]
    public function testSubscriptionGatedByFeatureFlag(): void
    {
        Feature::fake([], static function (): void {
            static::assertSame([], StorefrontRouteEventSubscriber::getSubscribedEvents());
        });

        Feature::fake(['v6.8.0.0'], static function (): void {
            $events = StorefrontRouteEventSubscriber::getSubscribedEvents();
            static::assertArrayHasKey(StorefrontRenderEvent::class, $events);
            static::assertSame(['render', -10], $events[StorefrontRenderEvent::class]);
        });
    }

    #[TestDox('render() re-dispatches the event under the route-name and per-scope prefixed names')]
    public function testRenderRedispatchesWithRouteAndScopeNames(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);

        $event = $this->createMock(StorefrontRenderEvent::class);
        $event->method('getRequest')->willReturn($request);

        $dispatchedNames = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(static function (object $evt, ?string $name = null) use (&$dispatchedNames): object {
                $dispatchedNames[] = $name;

                return $evt;
            });

        (new StorefrontRouteEventSubscriber($dispatcher))->render($event);

        static::assertSame(['frontend.home.page.render', 'storefront.scope.render'], $dispatchedNames);
    }
}
