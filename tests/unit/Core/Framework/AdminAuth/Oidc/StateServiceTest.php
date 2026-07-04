<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Oidc;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Oidc\StateService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(StateService::class)]
class StateServiceTest extends TestCase
{
    private const PROVIDER_ID = '0190f2c1c8e871ee87f4d1f3e1a2b3c4';

    public function testCreateAndConsumeRoundtrip(): void
    {
        $service = $this->createService();

        $stateData = $service->create(new Request(), self::PROVIDER_ID);

        static::assertNotSame('', $stateData['state']);
        static::assertNotSame('', $stateData['nonce']);
        static::assertNotSame($stateData['state'], $stateData['nonce']);

        $stored = $service->consume($this->callbackRequest($stateData['cookie']), $stateData['state']);

        static::assertSame($stateData['nonce'], $stored['nonce']);
        static::assertSame(self::PROVIDER_ID, $stored['providerId']);
    }

    public function testCookieIsScopedAndHttpOnly(): void
    {
        $cookie = $this->createService()->create(new Request(), self::PROVIDER_ID)['cookie'];

        static::assertSame(StateService::COOKIE_NAME, $cookie->getName());
        static::assertSame('/api/_action/admin-auth', $cookie->getPath());
        static::assertTrue($cookie->isHttpOnly());
        static::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
    }

    public function testConsumeRejectsAWrongState(): void
    {
        $service = $this->createService();
        $stateData = $service->create(new Request(), self::PROVIDER_ID);

        $this->expectExceptionObject(AdminAuthException::invalidOauthState());

        $service->consume($this->callbackRequest($stateData['cookie']), 'attacker-supplied-state');
    }

    public function testConsumeRejectsAMissingCookie(): void
    {
        $service = $this->createService();
        $stateData = $service->create(new Request(), self::PROVIDER_ID);

        $this->expectExceptionObject(AdminAuthException::invalidOauthState());

        $service->consume(new Request(), $stateData['state']);
    }

    public function testConsumeRejectsATamperedCookie(): void
    {
        $service = $this->createService();
        $stateData = $service->create(new Request(), self::PROVIDER_ID);

        $value = (string) $stateData['cookie']->getValue();
        [$payload, $signature] = explode('.', $value, 2);

        $tampered = json_decode((string) base64_decode($payload, true), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($tampered);
        $tampered['providerId'] = 'ffffffffffffffffffffffffffffffff';
        $tamperedValue = base64_encode(json_encode($tampered, \JSON_THROW_ON_ERROR)) . '.' . $signature;

        $request = new Request();
        $request->cookies->set(StateService::COOKIE_NAME, $tamperedValue);

        $this->expectExceptionObject(AdminAuthException::invalidOauthState());

        $service->consume($request, $stateData['state']);
    }

    public function testConsumeRejectsACookieSignedWithADifferentSecret(): void
    {
        $stateData = $this->createService('secret-a')->create(new Request(), self::PROVIDER_ID);

        $this->expectExceptionObject(AdminAuthException::invalidOauthState());

        $this->createService('secret-b')->consume($this->callbackRequest($stateData['cookie']), $stateData['state']);
    }

    public function testConsumeRejectsAnExpiredState(): void
    {
        $clock = new MockClock('2026-07-02 12:00:00');
        $service = $this->createService(clock: $clock);

        $stateData = $service->create(new Request(), self::PROVIDER_ID);

        $clock->modify('+11 minutes');

        $this->expectExceptionObject(AdminAuthException::invalidOauthState());

        $service->consume($this->callbackRequest($stateData['cookie']), $stateData['state']);
    }

    private function createService(string $secret = 'test-app-secret', ?MockClock $clock = null): StateService
    {
        return new StateService($secret, $clock ?? new MockClock());
    }

    private function callbackRequest(Cookie $cookie): Request
    {
        $request = new Request();
        $request->cookies->set($cookie->getName(), (string) $cookie->getValue());

        return $request;
    }
}
