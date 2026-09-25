<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\DeviceBoundSessionController;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSession;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionProofVerifier;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionService;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionStorage;
use Shopware\Storefront\Test\Framework\DeviceBoundSession\TestDeviceKey;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeviceBoundSessionController::class)]
class DeviceBoundSessionControllerTest extends TestCase
{
    private const SESSION_ID = '0190f0a1b2c37d8e9f0a1b2c3d4e5f60';

    private DeviceBoundSessionStorage&Stub $storage;

    private DeviceBoundSessionService $service;

    private TestDeviceKey $deviceKey;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->storage = static::createStub(DeviceBoundSessionStorage::class);
        $this->deviceKey = new TestDeviceKey();
        $this->context = Generator::generateSalesChannelContext(token: 'context-token');

        $this->service = $this->createService();
    }

    public function testRegistrationReturnsTheSessionConfigAndTheBoundCookie(): void
    {
        $this->storage->method('insert')->willReturn(true);
        $proof = $this->deviceKey->signRegistration($this->service->createRegistrationChallenge('context-token'));

        $response = $this->controller()->register($this->request(response: $proof), $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));

        $config = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($config);
        static::assertSame('/device-bound-session/refresh', $config['refresh_url']);
        static::assertSame('https://shop.example', $config['scope']['origin']);
        static::assertFalse($config['scope']['include_site']);
        static::assertContains(['type' => 'exclude', 'domain' => 'shop.example', 'path' => '/theme'], $config['scope']['scope_specification']);
        static::assertSame(['*'], $config['allowed_refresh_initiators']);

        $cookie = $this->cookie($response, $config['credentials'][0]['name']);
        static::assertSame('Path=/; Secure; HttpOnly; SameSite=Lax', $config['credentials'][0]['attributes']);
        static::assertSame('/', $cookie->getPath());
        static::assertTrue($cookie->isSecure());
        static::assertTrue($cookie->isHttpOnly());
        static::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        static::assertSame((new \DateTimeImmutable('2026-09-23 10:10:00'))->getTimestamp(), $cookie->getExpiresTime());
    }

    public function testRegistrationOverPlainHttpDescribesANonSecureCookie(): void
    {
        $this->storage->method('insert')->willReturn(true);
        $proof = $this->deviceKey->signRegistration($this->service->createRegistrationChallenge('context-token'));

        $response = $this->controller()->register($this->request(response: $proof, secure: false), $this->context);

        $config = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($config);
        static::assertSame('Path=/; HttpOnly; SameSite=Lax', $config['credentials'][0]['attributes']);
        static::assertFalse($this->cookie($response, $config['credentials'][0]['name'])->isSecure());
    }

    public function testRegistrationWithoutProofIsRejected(): void
    {
        $response = $this->controller()->register($this->request(), $this->context);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegistrationWithInvalidProofIsRejected(): void
    {
        $storage = $this->mockStorage();

        $storage->expects($this->never())->method('insert');

        $response = $this->controller()->register($this->request(response: $this->deviceKey->signRegistration('forged')), $this->context);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegistrationOfGuestVisitorsIsRejected(): void
    {
        $storage = $this->mockStorage();

        $this->context->assign(['customer' => null]);
        $proof = $this->deviceKey->signRegistration($this->service->createRegistrationChallenge('context-token'));

        $storage->expects($this->never())->method('insert');

        $response = $this->controller()->register($this->request(response: $proof), $this->context);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegistrationIsRejectedWhenDisabled(): void
    {
        $storage = $this->mockStorage();

        $proof = $this->deviceKey->signRegistration($this->service->createRegistrationChallenge('context-token'));

        $storage->expects($this->never())->method('insert');

        $response = $this->controller(enabled: false)->register($this->request(response: $proof), $this->context);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRefreshWithoutProofAnswersWithAChallenge(): void
    {
        $storage = $this->mockStorage();

        $storage->method('findById')->with(self::SESSION_ID)->willReturn($this->session());
        $storage->expects($this->once())->method('storeChallenge');

        $response = $this->controller()->refresh($this->request(sessionId: self::SESSION_ID), $this->context);

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertMatchesRegularExpression(
            '/^"[0-9a-f]{64}";id="' . self::SESSION_ID . '"$/',
            (string) $response->headers->get('Secure-Session-Challenge'),
        );
    }

    public function testRefreshWithInvalidProofAnswersWithANewChallenge(): void
    {
        $storage = $this->mockStorage();

        $storage->method('findById')->willReturn($this->session(challenge: 'refresh-challenge'));
        $storage->expects($this->never())->method('rotateCookie');

        $proof = (new TestDeviceKey())->signRefresh('refresh-challenge');
        $response = $this->controller()->refresh($this->request(sessionId: self::SESSION_ID, response: $proof), $this->context);

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertTrue($response->headers->has('Secure-Session-Challenge'));
    }

    public function testRefreshWithValidProofIssuesANewCookie(): void
    {
        $this->storage->method('findById')->willReturn($this->session(challenge: 'refresh-challenge'));
        $this->storage->method('rotateCookie')->willReturn(true);

        $proof = $this->deviceKey->signRefresh('refresh-challenge');
        $response = $this->controller()->refresh($this->request(sessionId: self::SESSION_ID, response: $proof), $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $this->cookie($response, 'sw-dbsc-0190f0a1b2c3')->getValue());
    }

    public function testRefreshOfUnknownSessionTerminatesIt(): void
    {
        $this->storage->method('findById')->willReturn(null);

        $response = $this->controller()->refresh($this->request(sessionId: self::SESSION_ID), $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"continue":false}', $response->getContent());
    }

    public function testRefreshOfAnotherSessionsBindingTerminatesItWithoutRemovingTheBinding(): void
    {
        $storage = $this->mockStorage();

        $storage->method('findById')->willReturn($this->session());
        $storage->expects($this->never())->method('delete');

        $context = Generator::generateSalesChannelContext(token: 'other-context-token');
        $response = $this->controller()->refresh($this->request(sessionId: self::SESSION_ID), $context);

        static::assertSame('{"continue":false}', $response->getContent());
    }

    public function testRefreshTerminatesTheSessionWhenDisabled(): void
    {
        $storage = $this->mockStorage();

        $storage->expects($this->never())->method('findById');

        $response = $this->controller(enabled: false)->refresh($this->request(sessionId: self::SESSION_ID), $this->context);

        static::assertSame('{"continue":false}', $response->getContent());
    }

    public function testEndpointsAreReachableDuringMaintenance(): void
    {
        $route = (new \ReflectionClass(DeviceBoundSessionController::class))->getAttributes(Route::class)[0]->newInstance();

        static::assertTrue($route->defaults[PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE]);
    }

    private function createService(): DeviceBoundSessionService
    {
        return new DeviceBoundSessionService(
            $this->storage,
            new DeviceBoundSessionProofVerifier(),
            new MockClock('2026-09-23 10:00:00'),
            secret: 'app-secret',
            cookieLifetime: 600,
            contextLifetime: 'P1D',
        );
    }

    private function mockStorage(): DeviceBoundSessionStorage&MockObject
    {
        $mock = $this->createMock(DeviceBoundSessionStorage::class);
        $this->storage = $mock;
        $this->service = $this->createService();

        return $mock;
    }

    private function controller(bool $enabled = true): DeviceBoundSessionController
    {
        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/device-bound-session/refresh');

        $container = new Container();
        $container->set('router', $router);

        $controller = new DeviceBoundSessionController($enabled, $this->service);
        $controller->setContainer($container);

        return $controller;
    }

    private function request(?string $sessionId = null, ?string $response = null, bool $secure = true): Request
    {
        $request = Request::create(($secure ? 'https' : 'http') . '://shop.example/device-bound-session/refresh', 'POST');

        if ($sessionId !== null) {
            $request->headers->set('Sec-Secure-Session-Id', '"' . $sessionId . '"');
        }

        if ($response !== null) {
            $request->headers->set('Secure-Session-Response', '"' . $response . '"');
        }

        return $request;
    }

    private function cookie(Response $response, string $name): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        static::fail(\sprintf('Cookie "%s" was not set', $name));
    }

    private function session(?string $challenge = null): DeviceBoundSession
    {
        return new DeviceBoundSession(
            id: self::SESSION_ID,
            contextToken: 'context-token',
            publicKey: $this->deviceKey->jwk(),
            cookieHash: hash('sha256', 'current'),
            previousCookieHash: null,
            challenge: $challenge,
            refreshedAt: new \DateTimeImmutable('2026-09-23 10:00:00'),
        );
    }
}
