<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1783073015McpToolsetSession;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1783073015McpToolsetSession::class)]
class Migration1783073015McpToolsetSessionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1783073015, (new Migration1783073015McpToolsetSession())->getCreationTimestamp());
    }

    public function testMigrationCreatesToolsetSessionTable(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::tableExists($this->connection, 'mcp_toolset_session'));

        $migration = new Migration1783073015McpToolsetSession();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'mcp_toolset_session'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'mcp_toolset_session', 'session_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'mcp_toolset_session', 'toolset_name'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'mcp_toolset_session', 'created_at'));
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `mcp_toolset_session`');
    }
}
