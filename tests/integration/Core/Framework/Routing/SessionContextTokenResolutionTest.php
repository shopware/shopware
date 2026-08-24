<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\SessionContextTokenSyncListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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

    private SalesChannelRequestContextResolver $resolver;

    private SessionContextTokenSyncListener $syncListener;

    private string $sessionName;

    protected function setUp(): void
    {
        $this->resolver = static::getContainer()->get(SalesChannelRequestContextResolver::class);
        $this->syncListener = static::getContainer()->get(SessionContextTokenSyncListener::class);

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

    public function testPrefersTheSalesChannelSuffixedSessionKey(): void
    {
        $suffixedToken = Random::getAlphanumericString(32);
        $plainToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $this->attachSession($request, [
            PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . TestDefaults::SALES_CHANNEL => $suffixedToken,
            PlatformRequest::HEADER_CONTEXT_TOKEN => $plainToken,
        ]);

        $this->resolve($request);

        static::assertSame($suffixedToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testFallsBackToThePlainSessionKey(): void
    {
        $plainToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $plainToken]);

        $this->resolve($request);

        static::assertSame($plainToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testWithoutSessionCookieTheDeclaredSessionSourceFails(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);
        // A Store API caller that does not send the storefront session cookie must not be able to
        // borrow a session, even when one happens to be attached to the request object.
        $request->cookies->remove($this->sessionName);

        $this->expectExceptionObject(
            RoutingException::sessionContextNotResolvable('the request carries no storefront session cookie')
        );

        $this->resolve($request);
    }

    public function testExplicitHeaderTokenWinsOverTheSession(): void
    {
        $sessionToken = Random::getAlphanumericString(32);
        $headerToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest($headerToken);
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);

        static::assertSame($headerToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($headerToken, $this->resolvedContext($request)->getToken());
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

    public function testRotationIsWrittenBackToTheSession(): void
    {
        $sessionToken = Random::getAlphanumericString(32);
        $rotatedToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $session = $this->attachSession($request, [
            PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . TestDefaults::SALES_CHANNEL => $sessionToken,
            PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken,
        ]);

        $this->resolve($request);

        $response = $this->respondWith($request, $rotatedToken);

        static::assertSame(
            $rotatedToken,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . TestDefaults::SALES_CHANNEL),
            'the sales channel suffixed key must follow the rotation'
        );
        static::assertSame(
            $rotatedToken,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            'the plain key is always kept in sync, like the storefront does'
        );

        static::assertTrue($response->headers->hasCacheControlDirective('private'));
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testRotationMigratesTheSessionId(): void
    {
        $sessionToken = Random::getAlphanumericString(32);
        $rotatedToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $session = $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);

        $initialId = $session->getId();
        static::assertSame(self::SESSION_ID, $initialId);

        $this->respondWith($request, $rotatedToken);

        $migratedId = $session->getId();
        static::assertNotSame(
            self::SESSION_ID,
            $migratedId,
            'a token rotation is a privilege boundary: a pre-planted session ID must not survive it'
        );
        static::assertSame(
            $migratedId,
            $session->get('sessionId'),
            'the sessionId key mirrors the current ID, like the storefront keeps it after login'
        );
        static::assertSame($rotatedToken, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
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
        // What strict mode does for native sessions: an unknown cookie value ends up on a session
        // with a freshly minted ID instead of resuming one.
        $this->attachSession(
            $request,
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken],
            cookieValue: 'a-stale-or-forged-session-id'
        );

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a storefront session holding a context token'
        ));

        $this->resolve($request);
    }

    public function testASessionWithoutAContextTokenFails(): void
    {
        $request = $this->createStoreApiRequest();
        // A resumable session that was not started by the storefront holds no context token.
        $this->attachSession($request, []);

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a storefront session holding a context token'
        ));

        $this->resolve($request);
    }

    public function testSharedCacheableRoutesRejectTheDeclaredSessionSource(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        // The store-api cache is keyed on headers and ignores cookies, so a cacheable route must
        // stay cookie independent - and the declared session source fails loudly instead.
        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the route is shared-cacheable and must stay independent of the session cookie'
        ));

        $this->resolve($request);
    }

    public function testAForeignHeaderTokenCannotRepointTheSession(): void
    {
        $sessionToken = Random::getAlphanumericString(32);
        $foreignToken = Random::getAlphanumericString(32);
        $rotatedForeignToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest($foreignToken);
        $session = $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);
        $this->respondWith($request, $rotatedForeignToken);

        static::assertSame(
            $sessionToken,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            'a caller that brought its own token must not repoint the shoppers session'
        );
    }

    public function testAResponseWithoutRotationKeepsItsCacheHeaders(): void
    {
        $headerToken = Random::getAlphanumericString(32);

        $request = $this->createStoreApiRequest($headerToken);
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->resolve($request);

        $response = $this->respondWith($request, $headerToken);

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
        // The accessor only trusts a session whose ID matches the cookie that resumed it, exactly
        // like a strict-mode native session.
        $storage->setId(self::SESSION_ID);
        $session = new Session($storage);

        foreach ($data as $key => $value) {
            $session->set($key, $value);
        }

        $request->setSession($session);
        // The cookie is what tells the resolver an existing session may be resumed.
        $request->cookies->set($this->sessionName, $cookieValue);

        return $session;
    }

    private function resolve(Request $request): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        try {
            $this->resolver->resolve($request);
        } finally {
            $requestStack->pop();
        }
    }

    /**
     * Runs the response listener for a response that reports $responseToken, i.e. what a rotating
     * route such as `POST /store-api/account/register` produces.
     */
    private function respondWith(Request $request, string $responseToken): Response
    {
        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $responseToken);

        $event = new ResponseEvent(
            static::getContainer()->get('kernel'),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        // The two handlers run at different kernel.response priorities in production; invoking
        // them in that order mirrors the event dispatch.
        $this->syncListener->syncSession($event);
        $this->syncListener->enforceCacheControl($event);

        return $response;
    }

    private function resolvedContext(Request $request): SalesChannelContext
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        static::assertInstanceOf(SalesChannelContext::class, $context);

        return $context;
    }
}
