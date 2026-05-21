<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Security;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Security\KeyMaterialGuard;

/**
 * @internal
 */
#[CoversClass(KeyMaterialGuard::class)]
class KeyMaterialGuardTest extends TestCase
{
    public function testRedactsTopLevelPrivateKeyKey(): void
    {
        $guard = new KeyMaterialGuard();
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'ucp',
            level: Level::Info,
            message: 'rotating',
            context: ['private_key' => 'super-secret', 'kid' => 'kid_1'],
            extra: [],
        );
        $processed = ($guard)($record);
        static::assertSame('[redacted]', $processed->context['private_key']);
        static::assertSame('kid_1', $processed->context['kid']);
    }

    public function testRedactsNestedPemEncrypted(): void
    {
        $guard = new KeyMaterialGuard();
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'ucp',
            level: Level::Info,
            message: 'rotating',
            context: ['nested' => ['pem_encrypted' => 'bytes', 'other' => 'ok']],
            extra: [],
        );
        $processed = ($guard)($record);
        static::assertSame('[redacted]', $processed->context['nested']['pem_encrypted']);
        static::assertSame('ok', $processed->context['nested']['other']);
    }

    public function testLeavesUnrelatedKeysUntouched(): void
    {
        $guard = new KeyMaterialGuard();
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'ucp',
            level: Level::Info,
            message: 'ok',
            context: ['public_jwk' => ['x' => 'X', 'y' => 'Y'], 'count' => 5],
            extra: [],
        );
        $processed = ($guard)($record);
        static::assertSame(['x' => 'X', 'y' => 'Y'], $processed->context['public_jwk']);
        static::assertSame(5, $processed->context['count']);
    }
}
