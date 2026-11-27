<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1795518429AddSystemCheckResultTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1795518429AddSystemCheckResultTable::class)]
class Migration1795518429AddSystemCheckResultTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `system_check_result`');
    }

    protected function tearDown(): void
    {
        (new Migration1795518429AddSystemCheckResultTable())->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1795518429, (new Migration1795518429AddSystemCheckResultTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1795518429AddSystemCheckResultTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'system_check_result'));
        static::assertSame(
            ['name', 'status', 'healthy', 'message', 'extra', 'checked_at'],
            $this->connection->fetchFirstColumn(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION',
                ['table' => 'system_check_result']
            )
        );
    }
}
