<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\DeviceBoundSession;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSession;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionProofVerifier;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionService;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionStorage;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionSubscriber;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeviceBoundSessionSubscriber::class)]
class DeviceBoundSessionSubscriberTest extends TestCase
{
    private const SESSION_ID = '0190f0a1b2c37d8e9f0a1b2c3d4e5f60';
    private const COOKIE_NAME = 'sw-dbsc-0190f0a1b2c3';

    private DeviceBoundSessionStorage&Stub $storage;

    private StorefrontSubscriber&Stub $storefrontSubscriber;

    private MockClock $clock;

    private RequestStack $requestStack;

    private DeviceBoundSessionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->storage = static::createStub(DeviceBoundSessionStorage::class);
        $this->storefrontSubscriber = static::createStub(StorefrontSubscriber::class);
        $this->clock = new MockClock('2026-09-23 10:00:00');
        $this->requestStack = new RequestStack();

        $this->subscriber = $this->createSubscriber(enabled: true);
    }

    public function testBoundSessionWithValidCookiePasses(): void
    {
        $storage = $this->mockStorage();
        $storefrontSubscriber = $this->mockStorefrontSubscriber();

        $storage->method('findByContextToken')->with('context-token')->willReturn($this->session());

        $storefrontSubscriber->expects($this->never())->method('updateSession');
        $storage->expects($this->never())->method('delete');

        $this->subscriber->enforceBinding($this->controllerEvent($this->storefrontRequest(cookieValue: 'current')));
    }

    public function testBoundSessionWithoutCookieIsRevoked(): void
    {
        $storage = $this->mockStorage();
        $storefrontSubscriber = $this->mockStorefrontSubscriber();

        $storage->method('findByContextToken')->willReturn($this->session());

        $storage->expects($this->once())->method('delete')->with(self::SESSION_ID);
        $storefrontSubscriber->expects($this->once())->method('updateSession')
            ->with(static::logicalNot(static::equalTo('context-token')), true);

        $this->subscriber->enforceBinding($this->controllerEvent($this->storefrontRequest()));
    }

    public function testBoundSessionWithExpiredCookieIsRevoked(): void
    {
        $storefrontSubscriber = $this->mockStorefrontSubscriber();

        $this->storage->method('findByContextToken')->willReturn($this->session());
        $this->clock->modify('+1 hour');

        $storefrontSubscriber->expects($this->once())->method('updateSession');

        $this->subscriber->enforceBinding($this->controllerEvent($this->storefrontRequest(cookieValue: 'current')));
    }

    public function testUnboundSessionPasses(): void
    {
        $storefrontSubscriber = $this->mockStorefrontSubscriber();

        $this->storage->method('findByContextToken')->willReturn(null);

        $storefrontSubscriber->expects($this->never())->method('updateSession');

        $this->subscriber->enforceBinding($this->controllerEvent($this->storefrontRequest()));
    }

    public function testDeviceBoundSessionRoutesAreNotGuarded(): void
    {
        $storage = $this->mockStorage();

        $storage->expects($this->never())->method('findByContextToken');

        $request = $this->storefrontRequest();
        $request->attributes->set('_route', 'frontend.device_bound_session.refresh');

        $this->subscriber->enforceBinding($this->controllerEvent($request));
    }

    public function testNonStorefrontRoutesAreNotGuarded(): void
    {
        $storage = $this->mockStorage();

        $storage->expects($this->never())->method('findByContextToken');

        $request = $this->storefrontRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['store-api']);

        $this->subscriber->enforceBinding($this->controllerEvent($request));
    }

    public function testNothingIsGuardedWhenDisabled(): void
    {
        $storage = $this->mockStorage();

        $storage->expects($this->never())->method('findByContextToken');

        $this->createSubscriber(enabled: false)->enforceBinding($this->controllerEvent($this->storefrontRequest()));
    }

    public function testRegistrationIsOfferedToLoggedInCustomersWithUnboundSession(): void
    {
        $this->storage->method('findByContextToken')->willReturn(null);
        $request = $this->storefrontRequest(loggedIn: true);
        $this->subscriber->enforceBinding($this->controllerEvent($request));

        $response = new Response();
        $this->subscriber->offerRegistration($this->responseEvent($request, $response));

        static::assertMatchesRegularExpression(
            '/^\(ES256\);path="\/device-bound-session\/register";challenge="\d+\.[\w-]+"$/',
            (string) $response->headers->get('Secure-Session-Registration'),
        );
    }

    public function testRegistrationIsNotOfferedToGuestVisitors(): void
    {
        $this->storage->method('findByContextToken')->willReturn(null);
        $request = $this->storefrontRequest(loggedIn: false);
        $this->subscriber->enforceBinding($this->controllerEvent($request));

        $response = new Response();
        $this->subscriber->offerRegistration($this->responseEvent($request, $response));

        static::assertFalse($response->headers->has('Secure-Session-Registration'));
    }

    public function testRegistrationIsNotOfferedForBoundSessions(): void
    {
        $this->storage->method('findByContextToken')->willReturn($this->session());
        $request = $this->storefrontRequest(cookieValue: 'current', loggedIn: true);
        $this->subscriber->enforceBinding($this->controllerEvent($request));

        $response = new Response();
        $this->subscriber->offerRegistration($this->responseEvent($request, $response));

        static::assertFalse($response->headers->has('Secure-Session-Registration'));
    }

    public function testRegistrationIsNotOfferedOnCacheableResponses(): void
    {
        $this->storage->method('findByContextToken')->willReturn(null);
        $request = $this->storefrontRequest(loggedIn: true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $this->subscriber->enforceBinding($this->controllerEvent($request));

        $response = new Response();
        $this->subscriber->offerRegistration($this->responseEvent($request, $response));

        static::assertFalse($response->headers->has('Secure-Session-Registration'));
    }

    public function testRegistrationIsNotOfferedOnErrorResponses(): void
    {
        $this->storage->method('findByContextToken')->willReturn(null);
        $request = $this->storefrontRequest(loggedIn: true);
        $this->subscriber->enforceBinding($this->controllerEvent($request));

        $response = new Response(null, Response::HTTP_NOT_FOUND);
        $this->subscriber->offerRegistration($this->responseEvent($request, $response));

        static::assertFalse($response->headers->has('Secure-Session-Registration'));
    }

    public function testRegistrationIsNotOfferedWhenTheGateDidNotRun(): void
    {
        $response = new Response();
        $this->subscriber->offerRegistration($this->responseEvent($this->storefrontRequest(loggedIn: true), $response));

        static::assertFalse($response->headers->has('Secure-Session-Registration'));
    }

    public function testLogoutRemovesTheBindingVerifiedDuringTheRequest(): void
    {
        $storage = $this->mockStorage();

        $storage->method('findByContextToken')->willReturn($this->session());
        $request = $this->storefrontRequest(cookieValue: 'current', loggedIn: true);
        $this->requestStack->push($request);
        $this->subscriber->enforceBinding($this->controllerEvent($request));

        $storage->expects($this->once())->method('delete')->with(self::SESSION_ID);

        $this->subscriber->removeBinding();
    }

    public function testLogoutOfUnboundSessionRemovesNothing(): void
    {
        $storage = $this->mockStorage();

        $storage->method('findByContextToken')->willReturn(null);
        $request = $this->storefrontRequest(loggedIn: true);
        $this->requestStack->push($request);
        $this->subscriber->enforceBinding($this->controllerEvent($request));

        $storage->expects($this->never())->method('delete');

        $this->subscriber->removeBinding();
    }

    private function mockStorage(): DeviceBoundSessionStorage&MockObject
    {
        $mock = $this->createMock(DeviceBoundSessionStorage::class);
        $this->storage = $mock;
        $this->subscriber = $this->createSubscriber(enabled: true);

        return $mock;
    }

    private function mockStorefrontSubscriber(): StorefrontSubscriber&MockObject
    {
        $mock = $this->createMock(StorefrontSubscriber::class);
        $this->storefrontSubscriber = $mock;
        $this->subscriber = $this->createSubscriber(enabled: true);

        return $mock;
    }

    private function createSubscriber(bool $enabled): DeviceBoundSessionSubscriber
    {
        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/device-bound-session/register');

        return new DeviceBoundSessionSubscriber(
            $enabled,
            new DeviceBoundSessionService($this->storage, new DeviceBoundSessionProofVerifier(), $this->clock, 'app-secret', 600, 'P1D'),
            $this->storefrontSubscriber,
            $router,
            $this->requestStack,
        );
    }

    private function storefrontRequest(?string $cookieValue = null, bool $loggedIn = false): Request
    {
        $request = new Request(cookies: $cookieValue !== null ? [self::COOKIE_NAME => $cookieValue] : []);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StorefrontRouteScope::ID]);
        $request->attributes->set('_route', 'frontend.account.home.page');
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'context-token');

        $context = Generator::generateSalesChannelContext(token: 'context-token');
        if (!$loggedIn) {
            $context->assign(['customer' => null]);
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);

        return $request;
    }

    private function controllerEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(static::createStub(HttpKernelInterface::class), static fn () => null, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function responseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    private function session(): DeviceBoundSession
    {
        return new DeviceBoundSession(
            id: self::SESSION_ID,
            contextToken: 'context-token',
            publicKey: [],
            cookieHash: hash('sha256', 'current'),
            previousCookieHash: null,
            challenge: null,
            refreshedAt: $this->clock->now(),
        );
    }
}
