<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1759482184AddLockTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1759482184AddLockTable::class)]
class Migration1759482184AddLockTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `lock_keys`;');
    }

    public function testMigration(): void
    {
        $sm = $this->connection->createSchemaManager();

        static::assertFalse($sm->tablesExist(['lock_keys']));

        $migration = new Migration1759482184AddLockTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($sm->tablesExist(['lock_keys']));

        $cols = $sm->listTableColumns('lock_keys');
        static::assertCount(3, $cols);
    }
}
