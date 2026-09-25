<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\DeviceBoundSession;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSession;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionProofVerifier;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionService;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionStorage;
use Shopware\Storefront\Test\Framework\DeviceBoundSession\TestDeviceKey;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeviceBoundSessionService::class)]
class DeviceBoundSessionServiceTest extends TestCase
{
    private DeviceBoundSessionStorage&Stub $storage;

    private MockClock $clock;

    private DeviceBoundSessionService $service;

    private TestDeviceKey $deviceKey;

    protected function setUp(): void
    {
        $this->storage = static::createStub(DeviceBoundSessionStorage::class);
        $this->clock = new MockClock('2026-09-23 10:00:00');
        $this->deviceKey = new TestDeviceKey();

        $this->service = $this->createService();
    }

    public function testRegistrationStoresTheDeviceKeyAndIssuesACookie(): void
    {
        $storage = $this->mockStorage();

        $challenge = $this->service->createRegistrationChallenge('context-token');

        $storage->expects($this->once())->method('insert')
            ->with(
                static::isString(),
                'context-token',
                $this->deviceKey->jwk(),
                static::isString(),
                $this->clock->now(),
                new \DateTimeImmutable('2026-09-24 10:10:00'),
            )
            ->willReturn(true);

        $registration = $this->service->register('context-token', $this->deviceKey->signRegistration($challenge));

        static::assertNotNull($registration);
        static::assertSame('context-token', $registration['session']->contextToken);
        static::assertTrue($this->service->isCookieValid($registration['session'], $registration['cookieValue']));
    }

    public function testRegistrationChallengeOfAnotherSessionIsRejected(): void
    {
        $storage = $this->mockStorage();

        $challenge = $this->service->createRegistrationChallenge('other-context-token');

        $storage->expects($this->never())->method('insert');

        static::assertNull($this->service->register('context-token', $this->deviceKey->signRegistration($challenge)));
    }

    public function testExpiredRegistrationChallengeIsRejected(): void
    {
        $storage = $this->mockStorage();

        $challenge = $this->service->createRegistrationChallenge('context-token');
        $this->clock->modify('+301 seconds');

        $storage->expects($this->never())->method('insert');

        static::assertNull($this->service->register('context-token', $this->deviceKey->signRegistration($challenge)));
    }

    public function testForgedRegistrationChallengeIsRejected(): void
    {
        $storage = $this->mockStorage();

        $challenge = ($this->clock->now()->getTimestamp() + 300) . '.forged';

        $storage->expects($this->never())->method('insert');

        static::assertNull($this->service->register('context-token', $this->deviceKey->signRegistration($challenge)));
    }

    public function testAlreadyBoundSessionCannotRegisterAnotherKey(): void
    {
        $challenge = $this->service->createRegistrationChallenge('context-token');

        $this->storage->method('insert')->willReturn(false);

        static::assertNull($this->service->register('context-token', $this->deviceKey->signRegistration($challenge)));
    }

    public function testRefreshChallengeIsStored(): void
    {
        $storage = $this->mockStorage();

        $session = $this->session(challenge: null);

        $storage->expects($this->once())->method('storeChallenge')
            ->with($session->id, static::matchesRegularExpression('/^[0-9a-f]{64}$/'));

        $this->service->createRefreshChallenge($session);
    }

    public function testRefreshRotatesTheCookie(): void
    {
        $storage = $this->mockStorage();

        $session = $this->session(challenge: 'refresh-challenge');

        $storage->expects($this->once())->method('rotateCookie')
            ->with($session->id, 'refresh-challenge', static::isString(), $this->clock->now(), new \DateTimeImmutable('2026-09-24 10:10:00'))
            ->willReturn(true);

        $cookieValue = $this->service->refresh($session, $this->deviceKey->signRefresh('refresh-challenge'));

        static::assertIsString($cookieValue);
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $cookieValue);
    }

    public function testRefreshAnsweringAnotherChallengeIsRejected(): void
    {
        $storage = $this->mockStorage();

        $session = $this->session(challenge: 'refresh-challenge');

        $storage->expects($this->never())->method('rotateCookie');

        static::assertNull($this->service->refresh($session, $this->deviceKey->signRefresh('stale-challenge')));
    }

    public function testRefreshWithoutIssuedChallengeIsRejected(): void
    {
        $storage = $this->mockStorage();

        $session = $this->session(challenge: null);

        $storage->expects($this->never())->method('rotateCookie');

        static::assertNull($this->service->refresh($session, $this->deviceKey->signRefresh('refresh-challenge')));
    }

    public function testRefreshSignedByAnotherDeviceIsRejected(): void
    {
        $storage = $this->mockStorage();

        $session = $this->session(challenge: 'refresh-challenge');

        $storage->expects($this->never())->method('rotateCookie');

        static::assertNull($this->service->refresh($session, (new TestDeviceKey())->signRefresh('refresh-challenge')));
    }

    public function testRefreshLosingTheRaceForTheChallengeIsRejected(): void
    {
        $session = $this->session(challenge: 'refresh-challenge');

        $this->storage->method('rotateCookie')->willReturn(false);

        static::assertNull($this->service->refresh($session, $this->deviceKey->signRefresh('refresh-challenge')));
    }

    public function testCurrentCookieIsValidUntilItsLifetimeAndGracePeriodPassed(): void
    {
        $session = $this->session(cookieValue: 'current');

        $this->clock->modify('+660 seconds');
        static::assertTrue($this->service->isCookieValid($session, 'current'));

        $this->clock->modify('+1 second');
        static::assertFalse($this->service->isCookieValid($session, 'current'));
    }

    public function testPreviousCookieIsOnlyValidDuringTheGracePeriod(): void
    {
        $session = $this->session(cookieValue: 'current', previousCookieValue: 'previous');

        $this->clock->modify('+60 seconds');
        static::assertTrue($this->service->isCookieValid($session, 'previous'));

        $this->clock->modify('+1 second');
        static::assertFalse($this->service->isCookieValid($session, 'previous'));
    }

    public function testMissingOrUnknownCookieIsInvalid(): void
    {
        $session = $this->session(cookieValue: 'current');

        static::assertFalse($this->service->isCookieValid($session, null));
        static::assertFalse($this->service->isCookieValid($session, ''));
        static::assertFalse($this->service->isCookieValid($session, 'unknown'));
    }

    public function testCookieNameIsDerivedFromTheSession(): void
    {
        static::assertSame('sw-dbsc-0190f0a1b2c3', $this->service->getCookieName($this->session()));
    }

    private function createService(): DeviceBoundSessionService
    {
        return new DeviceBoundSessionService(
            $this->storage,
            new DeviceBoundSessionProofVerifier(),
            $this->clock,
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

    private function session(string $cookieValue = 'current', ?string $previousCookieValue = null, ?string $challenge = null): DeviceBoundSession
    {
        return new DeviceBoundSession(
            id: '0190f0a1b2c37d8e9f0a1b2c3d4e5f60',
            contextToken: 'context-token',
            publicKey: $this->deviceKey->jwk(),
            cookieHash: hash('sha256', $cookieValue),
            previousCookieHash: $previousCookieValue !== null ? hash('sha256', $previousCookieValue) : null,
            challenge: $challenge,
            refreshedAt: $this->clock->now(),
        );
    }
}
