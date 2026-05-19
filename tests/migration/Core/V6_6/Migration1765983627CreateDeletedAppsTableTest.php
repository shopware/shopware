<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1765983627CreateDeletedAppsTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1765983627CreateDeletedAppsTable::class)]
class Migration1765983627CreateDeletedAppsTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1765983627, (new Migration1765983627CreateDeletedAppsTable())->getCreationTimestamp());
    }

    public function testGetMigrationTimestamp(): void
    {
        $migration = new Migration1765983627CreateDeletedAppsTable();

        static::assertSame(1765983627, $migration->getCreationTimestamp());
    }

    public function testAddTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `deleted_apps`');

        static::assertFalse($this->tableExists());

        $migration = new Migration1765983627CreateDeletedAppsTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->tableExists());
    }

    private function tableExists(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->tablesExist(['deleted_apps']);
    }
}
