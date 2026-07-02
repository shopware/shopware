<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\OAuth\Verifier;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallenge;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\WebAuthnVerifier;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnChallengeStore;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * The full assertion ceremony needs a real authenticator, so — like the FroshAdminAuth plugin — the
 * happy path is not simulated here. These tests cover the guard rails in front of the ceremony:
 * challenge transport, method gating and credential lookup.
 *
 * @internal
 */
#[CoversClass(WebAuthnVerifier::class)]
class WebAuthnVerifierTest extends TestCase
{
    private const APP_URL = 'https://admin.example.com';

    private WebAuthnService $webAuthnService;

    private WebAuthnChallengeStore $challengeStore;

    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->webAuthnService = new WebAuthnService('admin.example.com', 'Shopware Admin', [self::APP_URL]);
        $this->challengeStore = new WebAuthnChallengeStore('test-app-secret', new MockClock());
        $this->connection = $this->createMock(Connection::class);
    }

    public function testSupports(): void
    {
        $verifier = $this->createVerifier();

        static::assertTrue($verifier->supports('webauthn'));
        static::assertFalse($verifier->supports('password'));
        static::assertFalse($verifier->supports('totp'));
    }

    public function testVerifyPrimaryIsRejectedWhenPasskeyLoginIsDisabled(): void
    {
        $verifier = $this->createVerifier(webauthnEnabled: false);

        try {
            $verifier->verifyPrimary(['assertion' => '{}', 'challengeToken' => 'whatever']);
            static::fail('expected an access_denied OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('access_denied', $exception->getErrorType());
            static::assertSame('Passkey login is disabled.', $exception->getHint());
        }
    }

    public function testMissingAssertionIsRejected(): void
    {
        $verifier = $this->createVerifier();

        $this->expectException(OAuthServerException::class);

        $verifier->verifyPrimary(['challengeToken' => $this->issueLoginToken()]);
    }

    public function testMissingChallengeTokenIsRejected(): void
    {
        $verifier = $this->createVerifier();

        try {
            $verifier->verifyPrimary(['assertion' => $this->assertionJson()]);
            static::fail('expected an access_denied OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('access_denied', $exception->getErrorType());
            static::assertSame('No active WebAuthn login challenge.', $exception->getHint());
        }
    }

    public function testRegisterChallengeTokenCannotBeUsedForLogin(): void
    {
        $verifier = $this->createVerifier();

        $registerToken = $this->challengeStore->issue(
            $this->webAuthnService->serializeOptions($this->webAuthnService->createRequestOptions()),
            WebAuthnChallengeStore::PURPOSE_REGISTER,
            Uuid::randomHex()
        );

        try {
            $verifier->verifyPrimary(['assertion' => $this->assertionJson(), 'challengeToken' => $registerToken]);
            static::fail('expected an access_denied OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('access_denied', $exception->getErrorType());
        }
    }

    public function testMalformedAssertionJsonIsRejected(): void
    {
        $verifier = $this->createVerifier();

        try {
            $verifier->verifyPrimary(['assertion' => 'not-json', 'challengeToken' => $this->issueLoginToken()]);
            static::fail('expected an invalid_request OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_request', $exception->getErrorType());
        }
    }

    public function testAssertionWithoutCredentialIdIsRejected(): void
    {
        $verifier = $this->createVerifier();

        try {
            $verifier->verifyPrimary(['assertion' => '{"type":"public-key"}', 'challengeToken' => $this->issueLoginToken()]);
            static::fail('expected an invalid_request OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_request', $exception->getErrorType());
        }
    }

    public function testUnknownCredentialIsRejected(): void
    {
        $this->connection->method('fetchAssociative')->willReturn(false);

        $verifier = $this->createVerifier();

        try {
            $verifier->verifyPrimary(['assertion' => $this->assertionJson(), 'challengeToken' => $this->issueLoginToken()]);
            static::fail('expected an invalid_grant OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_grant', $exception->getErrorType());
        }
    }

    public function testSecondFactorLooksUpTheCredentialBoundToTheChallengedUser(): void
    {
        $userId = Uuid::randomHex();

        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                static::stringContains('user_id = :uid'),
                static::callback(static fn (array $params): bool => ($params['uid'] ?? null) === Uuid::fromHexToBytes($userId))
            )
            ->willReturn(false);

        $verifier = $this->createVerifier();

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor(
            $userId,
            ['assertion' => $this->assertionJson(), 'challengeToken' => $this->issueLoginToken()],
            $this->createChallenge($userId)
        );
    }

    public function testCorruptStoredCredentialIsRejectedAsAccessDenied(): void
    {
        $this->connection->method('fetchAssociative')->willReturn([
            'id' => Uuid::randomBytes(),
            'user_id' => Uuid::randomBytes(),
            'credential' => '{"broken": true}',
        ]);

        $verifier = $this->createVerifier();

        try {
            $verifier->verifyPrimary(['assertion' => $this->assertionJson(), 'challengeToken' => $this->issueLoginToken()]);
            static::fail('expected an access_denied OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('access_denied', $exception->getErrorType());
            static::assertSame('WebAuthn assertion failed.', $exception->getHint());
        }
    }

    private function createVerifier(bool $webauthnEnabled = true): WebAuthnVerifier
    {
        return new WebAuthnVerifier(
            $this->connection,
            $this->webAuthnService,
            $this->challengeStore,
            new MethodSettingsService(new StaticSystemConfigService(), ['webauthn' => $webauthnEnabled]),
            new MockClock(),
            self::APP_URL
        );
    }

    private function issueLoginToken(): string
    {
        $options = $this->webAuthnService->createRequestOptions();

        return $this->challengeStore->issue(
            $this->webAuthnService->serializeOptions($options),
            WebAuthnChallengeStore::PURPOSE_LOGIN
        );
    }

    private function assertionJson(): string
    {
        return (string) json_encode([
            'id' => rtrim(strtr(base64_encode(random_bytes(20)), '+/', '-_'), '='),
            'type' => 'public-key',
        ]);
    }

    private function createChallenge(string $userId): MfaChallenge
    {
        return new MfaChallenge(
            id: Uuid::randomHex(),
            userId: $userId,
            pendingJti: 'jti',
            allowedMethods: ['webauthn'],
            attempts: 0,
            consumed: false,
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );
    }
}
