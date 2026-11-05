<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1777535833McpToolResultCache;
use Shopware\Core\Migration\V6_7\Migration1790256569AddMcpToolResultCacheCreatedAtIndex;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1790256569AddMcpToolResultCacheCreatedAtIndex::class)]
class Migration1790256569AddMcpToolResultCacheCreatedAtIndexTest extends TestCase
{
    private const INDEX_NAME = 'idx.mcp_tool_result_cache.created_at';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790256569, (new Migration1790256569AddMcpToolResultCacheCreatedAtIndex())->getCreationTimestamp());
    }

    public function testMigrationCreatesExpectedIndexAndIsIdempotent(): void
    {
        (new Migration1777535833McpToolResultCache())->update($this->connection);
        $this->rollback();

        $migration = new Migration1790256569AddMcpToolResultCacheCreatedAtIndex();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'mcp_tool_result_cache', self::INDEX_NAME));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'mcp_tool_result_cache',
            self::INDEX_NAME,
            ['created_at'],
        ));
    }

    private function rollback(): void
    {
        if (!TableHelper::indexExists($this->connection, 'mcp_tool_result_cache', self::INDEX_NAME)) {
            return;
        }

        $this->connection->executeStatement('DROP INDEX `' . self::INDEX_NAME . '` ON `mcp_tool_result_cache`');
    }
}
