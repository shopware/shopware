<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\OAuth\Verifier;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallenge;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\RecoveryCodeVerifier;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(RecoveryCodeVerifier::class)]
class RecoveryCodeVerifierTest extends TestCase
{
    public function testSupports(): void
    {
        $verifier = new RecoveryCodeVerifier($this->createMock(Connection::class), new MockClock());

        static::assertTrue($verifier->supports('recovery_codes'));
        static::assertFalse($verifier->supports('recovery_code'));
        static::assertFalse($verifier->supports('totp'));
    }

    public function testPassesForValidCodeAndMarksItUsed(): void
    {
        $credential = $this->credentialWith(['ABCD1234', 'WXYZ5678']);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 'row-id', 'credential' => $credential]);

        $captured = null;
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$captured): int {
                $captured = $params['cred'] ?? null;

                return 1;
            });

        $verifier = new RecoveryCodeVerifier($connection, new MockClock());

        // Accepts the grouped form; the bare code matches the same stored hash.
        $verifier->verifySecondFactor(Uuid::randomHex(), ['code' => 'ABCD-1234'], $this->challenge());

        static::assertIsString($captured);
        $stored = json_decode($captured, true);
        static::assertIsArray($stored);
        static::assertNotNull($stored['codes'][0]['usedAt'], 'the used code must be marked');
        static::assertNull($stored['codes'][1]['usedAt'], 'other codes stay valid');
    }

    public function testRejectsWrongCode(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 'row-id', 'credential' => $this->credentialWith(['ABCD1234'])]);

        $verifier = new RecoveryCodeVerifier($connection, new MockClock());

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor(Uuid::randomHex(), ['code' => 'NOPE0000'], $this->challenge());
    }

    public function testRejectsAlreadyUsedCode(): void
    {
        $hash = password_hash('ABCD1234', \PASSWORD_DEFAULT);
        $credential = json_encode(['codes' => [['hash' => $hash, 'usedAt' => '2026-01-01T00:00:00+00:00']]], \JSON_THROW_ON_ERROR);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['id' => 'row-id', 'credential' => $credential]);

        $verifier = new RecoveryCodeVerifier($connection, new MockClock());

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor(Uuid::randomHex(), ['code' => 'ABCD-1234'], $this->challenge());
    }

    public function testRejectsWhenNoRecoveryCodesEnrolled(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $verifier = new RecoveryCodeVerifier($connection, new MockClock());

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor(Uuid::randomHex(), ['code' => 'ABCD1234'], $this->challenge());
    }

    public function testRejectsMissingCode(): void
    {
        $verifier = new RecoveryCodeVerifier($this->createMock(Connection::class), new MockClock());

        $this->expectException(OAuthServerException::class);

        $verifier->verifySecondFactor(Uuid::randomHex(), [], $this->challenge());
    }

    /**
     * @param list<string> $plainCodes
     */
    private function credentialWith(array $plainCodes): string
    {
        $codes = array_map(
            static fn (string $code): array => ['hash' => password_hash($code, \PASSWORD_DEFAULT), 'usedAt' => null],
            $plainCodes
        );

        return json_encode(['codes' => $codes], \JSON_THROW_ON_ERROR);
    }

    private function challenge(): MfaChallenge
    {
        return new MfaChallenge(
            id: 'challenge-id',
            userId: 'user-id',
            pendingJti: 'jti',
            allowedMethods: ['totp', 'recovery_codes'],
            attempts: 0,
            consumed: false,
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );
    }
}
