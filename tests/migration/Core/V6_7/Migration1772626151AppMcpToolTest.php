<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1772626151AppMcpTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1772626151AppMcpTool::class)]
class Migration1772626151AppMcpToolTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_tool_translation`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_tool`;');
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_mcp_tool'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_mcp_tool_translation'));

        $migration = new Migration1772626151AppMcpTool();
        static::assertSame(1772626151, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'app_mcp_tool'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'app_mcp_tool_translation'));

        static::assertCount(7, TableHelper::getTable($this->connection, 'app_mcp_tool')->columns);
        static::assertCount(6, TableHelper::getTable($this->connection, 'app_mcp_tool_translation')->columns);
    }
}
