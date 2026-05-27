<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Transport\Signature;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Transport\Signature\SignatureReplayGuard;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Pins server-side RFC 9421 replay protection. A captured signed request must
 * not be replayable inside the validity window. The guard is the ONLY layer
 * stopping that — without it any short-lived signature could be replayed at
 * will by anyone who saw it on the wire.
 *
 * @internal
 */
#[CoversClass(SignatureReplayGuard::class)]
class SignatureReplayGuardTest extends TestCase
{
    public function testInsertsFreshlySeenSignature(): void
    {
        $connection = $this->createMock(Connection::class);
        /** @var array{table: string, values: array<string, mixed>}|null $captured */
        $captured = null;
        $connection->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (string $table, array $values) use (&$captured): int {
                $captured = ['table' => $table, 'values' => $values];

                return 1;
            });

        $guard = new SignatureReplayGuard($connection);
        $guard->rememberOrThrow(
            salesChannelId: Uuid::randomHex(),
            kid: 'kid-1',
            signatureRaw: 'binary-signature',
            created: 1_700_000_000
        );

        static::assertNotNull($captured);
        static::assertSame('ucp_signature_nonce', $captured['table']);
        static::assertSame(
            // hash('sha256', 'binary-signature') is deterministic
            hash('sha256', 'binary-signature'),
            $captured['values']['signature_hash']
        );
        static::assertSame('kid-1', $captured['values']['kid']);
    }

    public function testRejectsDuplicateSignatureAsReplay(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('insert')->willThrowException(
            $this->createMock(UniqueConstraintViolationException::class)
        );

        $guard = new SignatureReplayGuard($connection);

        $this->expectExceptionObject(UcpException::signatureInvalid(
            'Signature replay detected — this signature has already been used'
        ));

        $guard->rememberOrThrow(
            salesChannelId: Uuid::randomHex(),
            kid: 'kid-1',
            signatureRaw: 'binary-signature',
            created: 1_700_000_000
        );
    }

    public function testDefaultsCreatedTimestampToNowWhenNullPassed(): void
    {
        $connection = $this->createMock(Connection::class);
        /** @var array<string, mixed>|null $captured */
        $captured = null;
        $connection->method('insert')->willReturnCallback(function ($_, array $values) use (&$captured): int {
            $captured = $values;

            return 1;
        });

        $beforeMs = (int) (microtime(true) * 1000);
        (new SignatureReplayGuard($connection))->rememberOrThrow(
            Uuid::randomHex(),
            'kid-1',
            'sig',
            null
        );
        $afterMs = (int) (microtime(true) * 1000);

        static::assertNotNull($captured);
        static::assertIsString($captured['created']);
        // `created` is rendered as `Y-m-d H:i:s.v`; parse it back and ensure
        // it lies within the test window.
        $written = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $captured['created']);
        static::assertNotFalse($written);
        $writtenMs = (int) ($written->format('U.v') * 1000);
        static::assertGreaterThanOrEqual($beforeMs - 1500, $writtenMs);
        static::assertLessThanOrEqual($afterMs + 1500, $writtenMs);
    }

    public function testRetentionWindowMatchesPublicConstant(): void
    {
        // Documented in the class — exposed as `RETENTION_SECONDS` for cleanup
        // tasks. The guard MUST not silently shrink the retention without a
        // matching cleanup task update, hence we assert the constant.
        static::assertGreaterThanOrEqual(
            SignatureReplayGuard::MAX_VALIDITY_WINDOW_SECONDS,
            SignatureReplayGuard::RETENTION_SECONDS,
            'Retention window must be at least as long as the maximum validity window.'
        );
    }
}
