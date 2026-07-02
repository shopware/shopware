<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Mfa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallenge;

/**
 * @internal
 */
#[CoversClass(MfaChallenge::class)]
class MfaChallengeTest extends TestCase
{
    public function testNotExpiredBeforeExpiry(): void
    {
        $challenge = $this->createChallenge(new \DateTimeImmutable('2026-01-01 12:00:00'));

        static::assertFalse($challenge->isExpired(new \DateTimeImmutable('2026-01-01 11:59:59')));
    }

    public function testExpiredAfterExpiry(): void
    {
        $challenge = $this->createChallenge(new \DateTimeImmutable('2026-01-01 12:00:00'));

        static::assertTrue($challenge->isExpired(new \DateTimeImmutable('2026-01-01 12:00:01')));
    }

    public function testExpiredExactlyAtBoundary(): void
    {
        // expiresAt <= now means the exact boundary counts as expired.
        $challenge = $this->createChallenge(new \DateTimeImmutable('2026-01-01 12:00:00'));

        static::assertTrue($challenge->isExpired(new \DateTimeImmutable('2026-01-01 12:00:00')));
    }

    public function testExposesConstructorValues(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $challenge = new MfaChallenge(
            id: 'challenge-id',
            userId: 'user-id',
            pendingJti: 'jti-123',
            allowedMethods: ['totp', 'webauthn'],
            attempts: 2,
            consumed: false,
            expiresAt: $expiresAt,
        );

        static::assertSame('challenge-id', $challenge->id);
        static::assertSame('user-id', $challenge->userId);
        static::assertSame('jti-123', $challenge->pendingJti);
        static::assertSame(['totp', 'webauthn'], $challenge->allowedMethods);
        static::assertSame(2, $challenge->attempts);
        static::assertFalse($challenge->consumed);
        static::assertSame($expiresAt, $challenge->expiresAt);
    }

    private function createChallenge(\DateTimeImmutable $expiresAt): MfaChallenge
    {
        return new MfaChallenge(
            id: 'challenge-id',
            userId: 'user-id',
            pendingJti: 'jti-123',
            allowedMethods: ['totp'],
            attempts: 0,
            consumed: false,
            expiresAt: $expiresAt,
        );
    }
}
