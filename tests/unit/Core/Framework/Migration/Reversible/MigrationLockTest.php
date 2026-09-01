<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\Reversible\MigrationLock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationLock::class)]
class MigrationLockTest extends TestCase
{
    public function testLockNameFitsTheMysqlLimitAndIsStablePerPlugin(): void
    {
        $name = MigrationLock::lockName('SwagReversible');

        // GET_LOCK names are limited to 64 characters, so the plugin name is hashed
        static::assertLessThanOrEqual(64, mb_strlen($name));
        static::assertSame($name, MigrationLock::lockName('SwagReversible'));
        static::assertNotSame($name, MigrationLock::lockName('SwagOther'));
    }

    public function testReturnsTheCallbackResult(): void
    {
        $lock = new MigrationLock($this->connectionHoldingTheLock());

        static::assertSame('result', $lock->synchronized('SwagReversible', static fn (): string => 'result'));
    }

    public function testFailsWhenTheLockCannotBeAcquired(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(0);

        $lock = new MigrationLock($connection, timeoutSeconds: 0);

        $this->expectExceptionObject(MigrationException::migrationLockNotAcquired('SwagReversible'));

        $lock->synchronized('SwagReversible', static fn (): bool => true);
    }

    public function testLetsTheCallbackExceptionBubbleUp(): void
    {
        $lock = new MigrationLock($this->connectionHoldingTheLock());

        $this->expectExceptionObject(new \RuntimeException('migration failed'));

        $lock->synchronized('SwagReversible', static function (): void {
            throw new \RuntimeException('migration failed');
        });
    }

    private function connectionHoldingTheLock(): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        return $connection;
    }
}
