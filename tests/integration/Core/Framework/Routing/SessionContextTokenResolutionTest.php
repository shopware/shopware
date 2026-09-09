<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopware\Core\Framework\Routing\SessionContextTokenSubscriber;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Store API requests resolve their context token from the storefront session when the caller opts
 * in via `sw-context-source: session` and sends the session cookie but no `sw-context-token`
 * header, and token rotations are written back into that session.
 *
 * @internal
 *
 * @see \Shopware\Core\Framework\Routing\SessionContextTokenAccessor
 */
#[Package('framework')]
class SessionContextTokenResolutionTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SESSION_ID = 'a-resumable-storefront-session-id';

    private const SUFFIXED_KEY = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . TestDefaults::SALES_CHANNEL;

    private SalesChannelRequestContextResolver $resolver;

    private SessionContextTokenSubscriber $subscriber;

    private string $sessionName;

    protected function setUp(): void
    {
        $this->resolver = static::getContainer()->get(SalesChannelRequestContextResolver::class);
        $this->subscriber = static::getContainer()->get(SessionContextTokenSubscriber::class);

        /** @var array<string, mixed> $sessionOptions */
        $sessionOptions = static::getContainer()->getParameter('session.storage.options');
        $this->sessionName = (string) ($sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME);
    }

    public function testResolvesTokenFromStorefrontSession(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);

        static::assertSame($sessionToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($sessionToken, $this->resolvedContext($request)->getToken());
        static::assertTrue($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
        static::assertTrue($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public function testUnderCustomerBindingTheSalesChannelKeyIsAuthoritative(): void
    {
        $this->enableCustomerBinding();
        $suffixedToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $this->attachSession($request, [
            self::SUFFIXED_KEY => $suffixedToken,
            PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32),
        ]);

        $this->resolve($request);

        static::assertSame($suffixedToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testWithoutCustomerBindingAStaleSalesChannelKeyIsIgnored(): void
    {
        $plainToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $this->attachSession($request, [
            self::SUFFIXED_KEY => Random::getAlphanumericString(32),
            PlatformRequest::HEADER_CONTEXT_TOKEN => $plainToken,
        ]);

        $this->resolve($request);

        static::assertSame($plainToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testUnderCustomerBindingAPlainOnlySessionHoldsNoTokenForTheSalesChannel(): void
    {
        $this->enableCustomerBinding();

        $request = $this->createStoreApiRequest();
        // a session created before the binding flag was switched on
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a storefront session holding a context token for this sales channel'
        ));

        $this->resolve($request);
    }

    public function testWithoutSessionCookieTheDeclaredSessionSourceFails(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);
        // a session attached to the request object but not backed by the cookie must not be borrowed
        $request->cookies->remove($this->sessionName);

        $this->expectExceptionObject(
            RoutingException::sessionContextNotResolvable('the request carries no storefront session cookie')
        );

        $this->resolve($request);
    }

    public function testDeclaringTheSessionSourceAlongsideATokenHeaderFails(): void
    {
        $request = $this->createStoreApiRequest(Random::getAlphanumericString(32));
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the request also carries a sw-context-token header; declare either the session or an explicit token as context source, not both'
        ));

        $this->resolve($request);
    }

    public function testStorefrontScopedRequestsAreNotSubjectToTheSessionSourceRules(): void
    {
        $headerToken = Random::getAlphanumericString(32);

        // storefront requests always carry a token header, only Store API requests are governed
        $request = $this->createStoreApiRequest($headerToken);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->resolve($request);

        static::assertSame($headerToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function fetchSiteProvider(): array
    {
        return [
            ['same-origin', true],
            ['same-site', true],
            ['cross-site', false],
            ['none', false],
        ];
    }

    #[DataProvider('fetchSiteProvider')]
    public function testFetchMetadataGatesTheSessionFallback(string $fetchSite, bool $shouldResolve): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $request->headers->set('Sec-Fetch-Site', $fetchSite);
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        if (!$shouldResolve) {
            $this->expectExceptionObject(
                RoutingException::sessionContextNotResolvable('the request is not a same-origin or same-site fetch')
            );
        }

        $this->resolve($request);

        static::assertSame($sessionToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testALoginIsWrittenBackToTheSession(): void
    {
        $this->enableCustomerBinding();
        $sessionToken = Random::getAlphanumericString(32);
        $loggedInToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $session = $this->attachSession($request, [
            self::SUFFIXED_KEY => $sessionToken,
            PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken,
        ]);

        $this->resolve($request);
        $this->login($request, $loggedInToken);

        static::assertSame($loggedInToken, $session->get(self::SUFFIXED_KEY), 'the sales channel key must follow the rotation');
        static::assertSame($loggedInToken, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN), 'the plain key is always kept in sync');
        static::assertSame($loggedInToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $response = $this->respond($request);

        static::assertTrue($response->headers->hasCacheControlDirective('private'));
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testARotationMigratesTheSessionId(): void
    {
        $request = $this->createStoreApiRequest();
        $session = $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->resolve($request);

        $initialId = $session->getId();
        static::assertSame(self::SESSION_ID, $initialId);

        $this->login($request, Random::getAlphanumericString(32));

        $migratedId = $session->getId();
        static::assertNotSame(
            self::SESSION_ID,
            $migratedId,
            'a token rotation is a privilege boundary: a pre-planted session ID must not survive it'
        );
        static::assertSame($migratedId, $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));
    }

    public function testALogoutDestroysTheSessionAndContinuesOnAFreshToken(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $session = $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);
        $this->logout($request);

        $token = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($token);
        static::assertSame(32, \strlen($token));
        static::assertNotSame($sessionToken, $token);
        static::assertNotSame(self::SESSION_ID, $session->getId());
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function missingOptInProvider(): iterable
    {
        yield 'no sw-context-source header at all' => [null];
        yield 'an unrecognized sw-context-source value' => ['token'];
    }

    #[DataProvider('missingOptInProvider')]
    public function testWithoutTheOptInHeaderATokenLessRequestKeepsItsClassicSemantics(?string $contextSource): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest(sessionOptIn: false);
        if ($contextSource !== null) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, $contextSource);
        }
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);

        static::assertNotSame(
            $sessionToken,
            $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            'without the explicit opt-in a token-less request must get a fresh throwaway context, never the shoppers session'
        );
        static::assertFalse($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
        static::assertFalse($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public function testACookieThatDoesNotResumeASessionFails(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        // strict mode: an unknown cookie ends up on a session with a fresh ID
        $this->attachSession(
            $request,
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken],
            cookieValue: 'a-stale-or-forged-session-id'
        );

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a storefront session holding a context token for this sales channel'
        ));

        $this->resolve($request);
    }

    public function testASessionWithoutAContextTokenFails(): void
    {
        $request = $this->createStoreApiRequest();
        // resumable, but not started by the storefront
        $this->attachSession($request, []);

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a storefront session holding a context token for this sales channel'
        ));

        $this->resolve($request);
    }

    public function testSharedCacheableRoutesResolveFromTheSessionButAreNeverStored(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $request->attributes->set('_route', 'store-api.product.search');
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);

        static::assertSame($sessionToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($sessionToken, $this->resolvedContext($request)->getToken());
        static::assertTrue($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));

        // the full kernel.response chain, so CacheResponseSubscriber runs before the no-store enforcement
        $response = new Response();
        static::getContainer()->get('event_dispatcher')->dispatch(
            new ResponseEvent(
                static::getContainer()->get('kernel'),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $response
            ),
            KernelEvents::RESPONSE
        );

        static::assertTrue($response->headers->hasCacheControlDirective('no-store'), (string) $response->headers->get('cache-control'));
        static::assertTrue($response->headers->hasCacheControlDirective('private'), (string) $response->headers->get('cache-control'));
        static::assertFalse($response->headers->hasCacheControlDirective('public'), (string) $response->headers->get('cache-control'));
        static::assertFalse($response->headers->hasCacheControlDirective('s-maxage'), (string) $response->headers->get('cache-control'));
    }

    public function testATokenHeaderWithoutTheSessionSourceLeavesTheSessionAlone(): void
    {
        $sessionToken = Random::getAlphanumericString(32);
        $foreignToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest($foreignToken, sessionOptIn: false);
        $session = $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);
        $this->login($request, Random::getAlphanumericString(32));

        static::assertSame($foreignToken, $this->resolvedContext($request)->getToken());
        static::assertSame(
            $sessionToken,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            'a caller that brought its own token must not repoint the shoppers session'
        );
        static::assertSame(self::SESSION_ID, $session->getId());
    }

    public function testAResponseWithoutSessionInvolvementKeepsItsCacheHeaders(): void
    {
        $request = $this->createStoreApiRequest(Random::getAlphanumericString(32), sessionOptIn: false);
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->resolve($request);

        $response = $this->respond($request);

        static::assertFalse($response->headers->hasCacheControlDirective('no-store'));
    }

    private function createStoreApiRequest(?string $contextToken = null, bool $sessionOptIn = true): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);

        if ($sessionOptIn) {
            $request->headers->set(
                PlatformRequest::HEADER_CONTEXT_SOURCE,
                SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION
            );
        }

        if ($contextToken !== null) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        }

        return $request;
    }

    /**
     * @param array<string, string> $data
     */
    private function attachSession(Request $request, array $data, string $cookieValue = self::SESSION_ID): SessionInterface
    {
        $storage = new MockArraySessionStorage();
        // the accessor only trusts a session whose ID matches the cookie
        $storage->setId(self::SESSION_ID);
        $session = new Session($storage);

        foreach ($data as $key => $value) {
            $session->set($key, $value);
        }

        $request->setSession($session);
        $request->cookies->set($this->sessionName, $cookieValue);

        return $session;
    }

    private function enableCustomerBinding(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);
    }

    private function resolve(Request $request): void
    {
        $this->onStack($request, fn () => $this->resolver->resolve($request));
    }

    private function login(Request $request, string $token): void
    {
        $this->onStack($request, fn () => $this->subscriber->onCustomerLogin(
            new CustomerLoginEvent($this->resolvedContext($request), new CustomerEntity(), $token)
        ));
    }

    private function logout(Request $request): void
    {
        $this->onStack($request, fn () => $this->subscriber->onCustomerLogout(
            new CustomerLogoutEvent($this->resolvedContext($request), new CustomerEntity())
        ));
    }

    private function respond(Request $request): Response
    {
        $response = new Response();

        $this->subscriber->enforceCacheControl(new ResponseEvent(
            static::getContainer()->get('kernel'),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        return $response;
    }

    private function onStack(Request $request, callable $action): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        try {
            $action();
        } finally {
            $requestStack->pop();
        }
    }

    private function resolvedContext(Request $request): SalesChannelContext
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        static::assertInstanceOf(SalesChannelContext::class, $context);

        return $context;
    }
}
