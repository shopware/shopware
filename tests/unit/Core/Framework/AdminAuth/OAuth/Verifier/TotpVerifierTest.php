<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\OAuth\Verifier;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallenge;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\TotpVerifier;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(TotpVerifier::class)]
class TotpVerifierTest extends TestCase
{
    private SecretEncryptor $encryptor;

    private string $secret;

    private TOTP $totp;

    protected function setUp(): void
    {
        $this->encryptor = new SecretEncryptor('test-app-secret');
        $this->totp = TOTP::createFromSecret('JBSWY3DPEHPK3PXP');
        $this->secret = $this->totp->getSecret();
    }

    public function testSupports(): void
    {
        $verifier = new TotpVerifier($this->createMock(Connection::class), $this->encryptor, new MockClock());

        static::assertTrue($verifier->supports('totp'));
        static::assertFalse($verifier->supports('webauthn'));
        static::assertFalse($verifier->supports('password'));
    }

    public function testVerifySecondFactorPassesForCorrectCode(): void
    {
        $encrypted = $this->encryptor->encrypt($this->secret);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([['id' => 'row-id', 'secret' => $encrypted]]);
        // Successful verification updates last_used_at; stub as no-op.
        $connection->method('executeStatement')->willReturn(1);

        $verifier = new TotpVerifier($connection, $this->encryptor, new MockClock());

        $code = $this->totp->now();

        // Should not throw.
        $verifier->verifySecondFactor($this->validUserId(), ['code' => $code], $this->challenge());

        $this->addToAssertionCount(1);
    }

    public function testVerifySecondFactorThrowsForWrongCode(): void
    {
        $encrypted = $this->encryptor->encrypt($this->secret);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([['id' => 'row-id', 'secret' => $encrypted]]);

        $verifier = new TotpVerifier($connection, $this->encryptor, new MockClock());

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor($this->validUserId(), ['code' => '000000'], $this->challenge());
    }

    public function testVerifySecondFactorThrowsForMissingCode(): void
    {
        $verifier = new TotpVerifier($this->createMock(Connection::class), $this->encryptor, new MockClock());

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor($this->validUserId(), [], $this->challenge());
    }

    public function testVerifySecondFactorThrowsForMalformedCode(): void
    {
        $verifier = new TotpVerifier($this->createMock(Connection::class), $this->encryptor, new MockClock());

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor($this->validUserId(), ['code' => 'abc'], $this->challenge());
    }

    public function testVerifySecondFactorThrowsWhenNoEnrollments(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $verifier = new TotpVerifier($connection, $this->encryptor, new MockClock());

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor($this->validUserId(), ['code' => $this->totp->now()], $this->challenge());
    }

    public function testVerifySecondFactorSkipsUndecryptableSecret(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([['id' => 'broken', 'secret' => 'not-decryptable']]);

        $verifier = new TotpVerifier($connection, $this->encryptor, new MockClock());

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor($this->validUserId(), ['code' => $this->totp->now()], $this->challenge());
    }

    private function validUserId(): string
    {
        return Uuid::randomHex();
    }

    private function challenge(): MfaChallenge
    {
        return new MfaChallenge(
            id: 'challenge-id',
            userId: 'user-id',
            pendingJti: 'jti',
            allowedMethods: ['totp'],
            attempts: 0,
            consumed: false,
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );
    }
}
