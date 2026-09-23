<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1777535833McpToolResultCache;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1777535833McpToolResultCache::class)]
class Migration1777535833McpToolResultCacheTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1777535833, (new Migration1777535833McpToolResultCache())->getCreationTimestamp());
    }

    public function testMigrationCreatesTheToolResultCacheTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `mcp_tool_result_cache`');
        static::assertFalse(TableHelper::tableExists($this->connection, 'mcp_tool_result_cache'));

        $migration = new Migration1777535833McpToolResultCache();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'mcp_tool_result_cache'));
        foreach (['id', 'session_id', 'mime_type', 'content', 'created_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'mcp_tool_result_cache', $column), $column);
        }
    }
}
