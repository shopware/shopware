<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1659256999CreateFlowTemplateTable;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1659256999CreateFlowTemplateTable
 */
class Migration1659256999CreateFlowTemplateTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1659256999CreateFlowTemplateTable();
        static::assertEquals('1659256999', $migration->getCreationTimestamp());
    }

    public function testTablesArePresent(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `flow_template`');

        $migration = new Migration1659256999CreateFlowTemplateTable();

        // should work as expected if executed multiple times
        $migration->update($this->connection);
        $migration->update($this->connection);

        $flowTemplateColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM flow_template'), 'Field');

        static::assertContains('id', $flowTemplateColumns);
        static::assertContains('name', $flowTemplateColumns);
        static::assertContains('config', $flowTemplateColumns);
        static::assertContains('created_at', $flowTemplateColumns);
        static::assertContains('updated_at', $flowTemplateColumns);
    }
}
