<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1790131000AddDeviceBoundSessionTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1790131000AddDeviceBoundSessionTable::class)]
class Migration1790131000AddDeviceBoundSessionTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `device_bound_session`');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790131000, (new Migration1790131000AddDeviceBoundSessionTable())->getCreationTimestamp());
    }

    public function testMigrationCreatesTableAndIsIdempotent(): void
    {
        $migration = new Migration1790131000AddDeviceBoundSessionTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'device_bound_session'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'device_bound_session', 'uniq.device_bound_session.context_token'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'device_bound_session', 'idx.device_bound_session.expires_at'));
    }
}
