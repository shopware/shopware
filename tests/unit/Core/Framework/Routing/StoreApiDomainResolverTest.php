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
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
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
    private const CURRENCY_ID = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';

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

    public function testResolvesLanguageAndCurrencyFromDomainHeader(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                static::anything(),
                [
                    'salesChannelId' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                    'url' => 'https://shop.example.com/de',
                ]
            )
            ->willReturn(['languageId' => self::LANGUAGE_ID, 'currencyId' => self::CURRENCY_ID]);

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de/',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        $request = $event->getRequest();
        static::assertSame(self::LANGUAGE_ID, $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertSame(self::CURRENCY_ID, $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
        // currency is applied as the domain default (attribute), not as an override (header)
        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CURRENCY_ID));
    }

    public function testExplicitLanguageHeaderIsKeptButCurrencyStillResolved(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturn(['languageId' => self::LANGUAGE_ID, 'currencyId' => self::CURRENCY_ID]);

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
            PlatformRequest::HEADER_LANGUAGE_ID => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        $request = $event->getRequest();
        static::assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertSame(self::CURRENCY_ID, $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
    }

    public function testEmptyContextHeadersAreTreatedAsAbsentAndResolvedFromDomain(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturn(['languageId' => self::LANGUAGE_ID, 'currencyId' => self::CURRENCY_ID]);

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
            PlatformRequest::HEADER_LANGUAGE_ID => '',
            PlatformRequest::HEADER_CURRENCY_ID => '',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        $request = $event->getRequest();
        static::assertSame(self::LANGUAGE_ID, $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertSame(self::CURRENCY_ID, $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
    }

    public function testExplicitCurrencyHeaderIsKeptButLanguageStillResolved(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturn(['languageId' => self::LANGUAGE_ID, 'currencyId' => self::CURRENCY_ID]);

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
            PlatformRequest::HEADER_CURRENCY_ID => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        $request = $event->getRequest();
        static::assertSame(self::LANGUAGE_ID, $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        // explicit currency wins, so the domain currency default must not be applied
        static::assertFalse($request->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
        static::assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $request->headers->get(PlatformRequest::HEADER_CURRENCY_ID));
    }

    public function testBothExplicitHeadersSkipDomainLookup(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
            PlatformRequest::HEADER_LANGUAGE_ID => self::LANGUAGE_ID,
            PlatformRequest::HEADER_CURRENCY_ID => self::CURRENCY_ID,
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertFalse($event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
    }

    public function testRequestWithoutDomainHeaderIsIgnored(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, []);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertFalse($event->getRequest()->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertFalse($event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
    }

    public function testEmptyDomainHeaderIsIgnored(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => '   ',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertFalse($event->getRequest()->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertFalse($event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
    }

    public function testDomainHeaderIsTrimmedBeforeLookup(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                static::anything(),
                [
                    'salesChannelId' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                    'url' => 'https://shop.example.com/de',
                ]
            )
            ->willReturn(['languageId' => self::LANGUAGE_ID, 'currencyId' => self::CURRENCY_ID]);

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => '  https://shop.example.com/de  ',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertSame(self::LANGUAGE_ID, $event->getRequest()->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testNonStoreApiRequestIsIgnored(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $event = $this->createEvent([ApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertFalse($event->getRequest()->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testRequestWithoutSalesChannelIdIsIgnored(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $event = $this->createEvent([StoreApiRouteScope::ID], null, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
        ]);

        $this->createResolver($connection)->resolveDomain($event);

        static::assertFalse($event->getRequest()->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testThrowsWhenDomainDoesNotMatchSalesChannel(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://unknown.example.com',
        ]);

        $this->expectExceptionObject(RoutingException::salesChannelDomainNotFound('https://unknown.example.com'));

        $this->createResolver($connection)->resolveDomain($event);
    }

    public function testSwitchedLanguageInContextBeatsDomain(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturn(['languageId' => self::LANGUAGE_ID, 'currencyId' => self::CURRENCY_ID]);

        $persister = $this->createMock(SalesChannelContextPersister::class);
        $persister->method('load')->willReturn([SalesChannelContextService::LANGUAGE_ID => 'cccccccccccccccccccccccccccccccc']);

        $event = $this->createEvent([StoreApiRouteScope::ID], TestDefaults::SALES_CHANNEL, [
            PlatformRequest::HEADER_DOMAIN => 'https://shop.example.com/de',
            PlatformRequest::HEADER_CONTEXT_TOKEN => 'ctx-token',
        ]);

        $this->createResolver($connection, $persister)->resolveDomain($event);

        $request = $event->getRequest();
        // a language the customer switched to wins over the domain, but the domain currency still applies
        static::assertFalse($request->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertSame(self::CURRENCY_ID, $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
    }

    private function createResolver(Connection $connection, ?SalesChannelContextPersister $persister = null): StoreApiDomainResolver
    {
        return new StoreApiDomainResolver(
            $connection,
            $persister ?? $this->createMock(SalesChannelContextPersister::class),
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
