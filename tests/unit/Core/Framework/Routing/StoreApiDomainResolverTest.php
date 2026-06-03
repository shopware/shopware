<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\StoreApiDomainResolver;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiDomainResolver::class)]
class StoreApiDomainResolverTest extends TestCase
{
    private const LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

    public function testSubscribesToControllerEventWithCorrectPriority(): void
    {
        $events = StoreApiDomainResolver::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::CONTROLLER, $events);
        static::assertSame('resolveDomain', $events[KernelEvents::CONTROLLER][0]);
        static::assertSame(
            KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_STORE_API_DOMAIN_RESOLVE,
            $events[KernelEvents::CONTROLLER][1]
        );
    }

    public function testResolvesLanguageFromDomainHeader(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                static::anything(),
                [
                    'salesChannelId' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                    'url' => 'https://shop.example.com/de',
                ]
            )
            ->willReturn(self::LANGUAGE_ID);

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de/',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertSame(self::LANGUAGE_ID, $event->getRequest()->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testExplicitLanguageHeaderTakesPrecedence(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
            PlatformRequest::HEADER_LANGUAGE_ID => self::LANGUAGE_ID,
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertSame(self::LANGUAGE_ID, $event->getRequest()->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testRequestWithoutDomainHeaderIsIgnored(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, []);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertFalse($event->getRequest()->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testNonStoreApiRequestIsIgnored(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $event = $this->createEvent([ApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertFalse($event->getRequest()->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testRequestWithoutSalesChannelIdIsIgnored(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $event = $this->createEvent([StoreApiRouteScope::ID], null, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertFalse($event->getRequest()->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testThrowsWhenDomainDoesNotMatchSalesChannel(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://unknown.example.com',
        ]);

        $this->expectExceptionObject(RoutingException::salesChannelDomainNotFound('https://unknown.example.com'));

        $this->createResolver($connection)->resolveDomain($event);
    }

    private function createResolver(Connection $connection): StoreApiDomainResolver
    {
        return new StoreApiDomainResolver(
            $connection,
            new RouteScopeRegistry([new StoreApiRouteScope(), new ApiRouteScope()])
        );
    }

    /**
     * @param list<string> $routeScopes
     * @param array<string, string> $headers
     */
    private function createEvent(array $routeScopes, ?string $salesChannelId, array $headers): ControllerEvent
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $routeScopes);

        if ($salesChannelId !== null) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);
        }

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }
}
