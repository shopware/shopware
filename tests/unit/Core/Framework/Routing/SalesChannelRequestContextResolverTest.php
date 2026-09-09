<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RequestContextResolverInterface;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelRequestContextResolver::class)]
class SalesChannelRequestContextResolverTest extends TestCase
{
    #[TestDox('Store API context resolution leaves the session untouched')]
    #[DataProvider('sessionStateProvider')]
    public function testResolutionLeavesSessionUntouched(bool $sessionAlreadyInstantiated): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (SalesChannelContextServiceParameters $parameters) use ($context): SalesChannelContext {
                static::assertNull($parameters->getImitatingUserId());

                return $context;
            });

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'test-token');
        $storage = new MockArraySessionStorage();
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use ($storage, &$factoryCalls): Session {
            ++$factoryCalls;

            return new Session($storage);
        });

        if ($sessionAlreadyInstantiated) {
            $request->getSession();
        }
        $factoryCallsBeforeResolve = $factoryCalls;

        $resolver = new SalesChannelRequestContextResolver(
            static::createStub(RequestContextResolverInterface::class),
            $contextService,
            new EventDispatcher(),
            new RouteScopeRegistry([new StoreApiRouteScope()]),
            new SessionContextTokenAccessor([], true, new StaticSystemConfigService())
        );

        $resolver->resolve($request);

        static::assertFalse($storage->isStarted(), 'Store API context resolution must not start the session.');
        static::assertSame($factoryCallsBeforeResolve, $factoryCalls, 'Store API context resolution must not invoke the lazy session factory.');
    }

    public static function sessionStateProvider(): \Generator
    {
        yield 'request has only the lazy session factory' => [false];
        yield 'session was instantiated but not started' => [true];
    }

    public function testEmptyLanguageAndCurrencyHeadersAreIgnored(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (SalesChannelContextServiceParameters $parameters) use ($context): SalesChannelContext {
                static::assertSame(TestDefaults::SALES_CHANNEL, $parameters->getSalesChannelId());
                static::assertSame('test-token', $parameters->getToken());
                static::assertNull($parameters->getLanguageId());
                static::assertNull($parameters->getOverwriteCurrencyId());

                return $context;
            });

        $decorated = $this->createMock(RequestContextResolverInterface::class);
        $decorated
            ->expects($this->never())
            ->method('resolve');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'test-token');
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, '');
        $request->headers->set(PlatformRequest::HEADER_CURRENCY_ID, '');

        $resolver = new SalesChannelRequestContextResolver(
            $decorated,
            $contextService,
            new EventDispatcher(),
            new RouteScopeRegistry([new StoreApiRouteScope()]),
            new SessionContextTokenAccessor([], true, new StaticSystemConfigService())
        );

        $resolver->resolve($request);
    }

    public function testASessionSourcedRequestResolvesTheSessionsToken(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (SalesChannelContextServiceParameters $parameters) use ($context): SalesChannelContext {
                static::assertSame('the-sessions-token', $parameters->getToken());

                return $context;
            });

        $request = $this->sessionSourcedRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'the-sessions-token']);

        $this->resolver($contextService)->resolve($request);

        static::assertSame('the-sessions-token', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertTrue($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
        static::assertTrue($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public function testASessionSourcedRequestMustNotCarryATokenHeader(): void
    {
        $request = $this->sessionSourcedRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'the-sessions-token']);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'an-explicit-token');

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the request also carries a sw-context-token header; declare either the session or an explicit token as context source, not both'
        ));

        $this->resolver($this->untouchedContextService())->resolve($request);
    }

    public function testASessionSourcedRequestFailsWhenTheSessionIsNotEligible(): void
    {
        $request = $this->sessionSourcedRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'the-sessions-token']);
        $request->cookies->remove('session-');

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable('the request carries no storefront session cookie'));

        $this->resolver($this->untouchedContextService())->resolve($request);
    }

    public function testASessionSourcedRequestFailsWhenTheSessionHoldsNoToken(): void
    {
        $request = $this->sessionSourcedRequest([]);

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a storefront session holding a context token for this sales channel'
        ));

        $this->resolver($this->untouchedContextService())->resolve($request);
    }

    private function resolver(SalesChannelContextServiceInterface $contextService): SalesChannelRequestContextResolver
    {
        return new SalesChannelRequestContextResolver(
            static::createStub(RequestContextResolverInterface::class),
            $contextService,
            new EventDispatcher(),
            new RouteScopeRegistry([new StoreApiRouteScope()]),
            new SessionContextTokenAccessor(['name' => 'session-'], true, new StaticSystemConfigService())
        );
    }

    private function untouchedContextService(): SalesChannelContextServiceInterface
    {
        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->expects($this->never())->method('get');

        return $contextService;
    }

    /**
     * @param array<string, string> $sessionData
     */
    private function sessionSourcedRequest(array $sessionData): Request
    {
        $storage = new MockArraySessionStorage();
        $storage->setId('a-resumable-session');
        $session = new Session($storage);
        foreach ($sessionData as $key => $value) {
            $session->set($key, $value);
        }

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
        $request->cookies->set('session-', 'a-resumable-session');
        $request->setSession($session);

        return $request;
    }
}
