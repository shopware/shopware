<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopware\Core\Framework\Routing\SessionContextTokenSubscriber;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SessionContextTokenSubscriber::class)]
class SessionContextTokenSubscriberTest extends TestCase
{
    private const BINDING_ENABLED = ['core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true];

    public function testHasEvents(): void
    {
        static::assertSame([
            KernelEvents::REQUEST => [['startSession', 40]],
            KernelEvents::RESPONSE => [['enforceCacheControl', -1600]],
            CustomerLoginEvent::class => 'onCustomerLogin',
            CustomerLogoutEvent::class => 'onCustomerLogout',
            SalesChannelContextResolvedEvent::class => 'onContextResolved',
        ], SessionContextTokenSubscriber::getSubscribedEvents());
    }

    public function testStartSessionStampsTheSessionIdAndMintsAToken(): void
    {
        $request = $this->ownerRequest('sales-channel-a');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request])->startSession($this->requestEvent($request));

        $session = $request->getSession();
        static::assertTrue($session->has(SessionContextTokenAccessor::SESSION_ID_KEY));

        $token = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertNotNull($token);
        static::assertSame(32, \strlen($token));
        static::assertSame($token, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testStartSessionIgnoresRequestsWithoutTheOwnerMarker(): void
    {
        $request = new Request();
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use (&$factoryCalls): Session {
            ++$factoryCalls;

            return new Session(new MockArraySessionStorage());
        });

        $this->subscriber([$request])->startSession($this->requestEvent($request));

        static::assertSame(0, $factoryCalls);
        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testASubRequestGetsTheMainRequestsToken(): void
    {
        $mainRequest = $this->ownerRequest('sales-channel-a');
        $session = new Session(new MockArraySessionStorage());
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'main-token');
        $mainRequest->setSession($session);

        $subRequest = new Request();

        $this->subscriber([$mainRequest, $subRequest])
            ->startSession($this->requestEvent($subRequest, HttpKernelInterface::SUB_REQUEST));

        static::assertSame('main-token', $mainRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('main-token', $subRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testWithoutBindingTheTokenLivesInThePlainKeyOnly(): void
    {
        $request = $this->ownerRequest('sales-channel-a');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request])->startSession($this->requestEvent($request));

        static::assertTrue($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-a'));
    }

    public function testWithBindingTheTokenLivesInTheChannelKey(): void
    {
        $request = $this->ownerRequest('sales-channel-a');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request], self::BINDING_ENABLED)->startSession($this->requestEvent($request));

        $session = $request->getSession();
        $channelToken = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-a');
        static::assertNotNull($channelToken);
        static::assertSame($channelToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($channelToken, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN), 'the plain key mirrors the channel being browsed');
    }

    public function testWithBindingTokensSurviveSwitchingChannels(): void
    {
        $session = new Session(new MockArraySessionStorage());

        $requestA = $this->ownerRequest('sales-channel-a');
        $requestA->setSession($session);
        $this->subscriber([$requestA], self::BINDING_ENABLED)->startSession($this->requestEvent($requestA));
        $tokenA = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-a');

        $requestB = $this->ownerRequest('sales-channel-b');
        $requestB->setSession($session);
        $this->subscriber([$requestB], self::BINDING_ENABLED)->startSession($this->requestEvent($requestB));
        $tokenB = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-b');

        static::assertNotNull($tokenA);
        static::assertNotNull($tokenB);
        static::assertNotSame($tokenA, $tokenB);

        $requestA2 = $this->ownerRequest('sales-channel-a');
        $requestA2->setSession($session);
        $this->subscriber([$requestA2], self::BINDING_ENABLED)->startSession($this->requestEvent($requestA2));

        static::assertSame($tokenA, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-a'), 'returning to a channel resumes its token');
        static::assertSame($tokenA, $requestA2->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($tokenB, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-b'));
    }

    public function testLoginRotatesTheTokenAndTheSessionId(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'logged-in');
        $request = $this->ownerRequest($context->getSalesChannelId());
        $session = $this->sessionWithId('before-login');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'anonymous');
        $request->setSession($session);

        $this->subscriber([$request])->onCustomerLogin(new CustomerLoginEvent($context, new CustomerEntity(), 'logged-in'));

        static::assertSame('logged-in', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('logged-in', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('before-login', $session->getId(), 'a login is a privilege boundary, the session ID must not survive it');
        static::assertSame($session->getId(), $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));
    }

    public function testLoginWithBindingStoresTheTokenInTheChannelKey(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'logged-in');
        $request = $this->ownerRequest($context->getSalesChannelId());
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request], self::BINDING_ENABLED)
            ->onCustomerLogin(new CustomerLoginEvent($context, new CustomerEntity(), 'logged-in'));

        $session = $request->getSession();
        static::assertSame('logged-in', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $context->getSalesChannelId()));
        static::assertSame('logged-in', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testLogoutContinuesOnAFreshTokenAndANewSessionId(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'the-routes-own-token');
        $request = $this->ownerRequest($context->getSalesChannelId());
        $session = $this->sessionWithId('logged-in-session');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'logged-in');
        $request->setSession($session);

        $this->subscriber([$request])->onCustomerLogout(new CustomerLogoutEvent($context, new CustomerEntity()));

        $token = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($token);
        static::assertSame(32, \strlen($token));
        static::assertNotSame('logged-in', $token);
        static::assertSame($token, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('logged-in-session', $session->getId());
    }

    public function testASwappedTokenIsFollowedIntoTheSession(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'fresh');
        $request = $this->ownerRequest($context->getSalesChannelId());
        $session = new Session(new MockArraySessionStorage());
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'expired');
        $request->setSession($session);

        $this->subscriber([$request])->onContextResolved(new SalesChannelContextResolvedEvent($context, 'expired'));

        static::assertSame('fresh', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('fresh', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAnUnchangedTokenLeavesTheSessionAlone(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'current');
        $request = $this->ownerRequest($context->getSalesChannelId());
        $session = $this->sessionWithId('stable');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'current');
        $request->setSession($session);

        $this->subscriber([$request])->onContextResolved(new SalesChannelContextResolvedEvent($context, 'current'));

        static::assertSame('stable', $session->getId());
        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRotationWithoutARequestIsIgnored(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'logged-in');

        $this->subscriber([])->onCustomerLogin(new CustomerLoginEvent($context, new CustomerEntity(), 'logged-in'));

        $this->expectNotToPerformAssertions();
    }

    public function testRotationOnAPlainStoreApiRequestLeavesTheSessionAlone(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'logged-in');
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]);
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use (&$factoryCalls): Session {
            ++$factoryCalls;

            return new Session(new MockArraySessionStorage());
        });

        $this->subscriber([$request])->onCustomerLogin(new CustomerLoginEvent($context, new CustomerEntity(), 'logged-in'));

        static::assertSame(0, $factoryCalls, 'a Store API request that did not declare the session as its source never touches it');
        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAnOwnerWithoutAnInitializedSessionIsLeftAlone(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'logged-in');
        $request = $this->ownerRequest($context->getSalesChannelId());
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use (&$factoryCalls): Session {
            ++$factoryCalls;

            return new Session(new MockArraySessionStorage());
        });

        $this->subscriber([$request])->onCustomerLogin(new CustomerLoginEvent($context, new CustomerEntity(), 'logged-in'));

        static::assertSame(0, $factoryCalls);
    }

    public function testTheKillSwitchDoesNotAffectTheOwner(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'logged-in');
        $request = $this->ownerRequest($context->getSalesChannelId());
        $request->setSession(new Session(new MockArraySessionStorage()));
        $subscriber = $this->subscriber([$request], enabled: false);

        $subscriber->startSession($this->requestEvent($request));
        static::assertNotNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $subscriber->onCustomerLogin(new CustomerLoginEvent($context, new CustomerEntity(), 'logged-in'));
        static::assertSame('logged-in', $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testTheKillSwitchStopsBorrowers(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'logged-in');
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
        $request->cookies->set('session-', 'resumable');
        $session = $this->sessionWithId('resumable');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'anonymous');
        $request->setSession($session);

        $this->subscriber([$request], enabled: false)
            ->onCustomerLogin(new CustomerLoginEvent($context, new CustomerEntity(), 'logged-in'));

        static::assertSame('anonymous', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('resumable', $session->getId());
    }

    public function testSessionResolvedStoreApiResponsesAreNeverSharedCacheable(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
            SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION => true,
        ]);
        $response = new Response();
        $response->headers->set('Cache-Control', 'public, s-maxage=1800');

        $this->subscriber([$request])->enforceCacheControl($this->responseEvent($request, $response));

        static::assertTrue($response->headers->hasCacheControlDirective('private'));
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        static::assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testOtherStoreApiResponsesKeepTheirCacheHeaders(): void
    {
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]);
        $response = new Response();
        $response->headers->set('Cache-Control', 'public, s-maxage=1800');

        $this->subscriber([$request])->enforceCacheControl($this->responseEvent($request, $response));

        static::assertTrue($response->headers->hasCacheControlDirective('public'));
        static::assertFalse($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testResponsesOutsideTheStoreApiAreNotTouched(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID],
            SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION => true,
        ]);
        $response = new Response();
        $response->headers->set('Cache-Control', 'public, s-maxage=1800');

        $this->subscriber([$request])->enforceCacheControl($this->responseEvent($request, $response));

        static::assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    /**
     * @param list<Request> $requests
     * @param array<string, mixed> $config
     */
    private function subscriber(array $requests, array $config = [], bool $enabled = true): SessionContextTokenSubscriber
    {
        return new SessionContextTokenSubscriber(
            new SessionContextTokenAccessor(['name' => 'session-'], $enabled, new StaticSystemConfigService($config)),
            new RequestStack($requests),
            new RouteScopeRegistry([new StoreApiRouteScope(), new ApiRouteScope()])
        );
    }

    private function ownerRequest(?string $salesChannelId = null): Request
    {
        $attributes = [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true];

        if ($salesChannelId !== null) {
            $attributes[PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID] = $salesChannelId;
        }

        return new Request(attributes: $attributes);
    }

    private function sessionWithId(string $id): Session
    {
        $storage = new MockArraySessionStorage();
        $storage->setId($id);

        return new Session($storage);
    }

    private function requestEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(static::createStub(HttpKernelInterface::class), $request, $type);
    }

    private function responseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
