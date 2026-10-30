<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1791310428McpListVersion;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1791310428McpListVersion::class)]
class Migration1791310428McpListVersionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791310428, (new Migration1791310428McpListVersion())->getCreationTimestamp());
    }

    public function testMigrationCreatesListVersionTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `mcp_list_version`');

        $migration = new Migration1791310428McpListVersion();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'mcp_list_version'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'mcp_list_version', 'list'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'mcp_list_version', 'version'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'mcp_list_version', 'updated_at'));
    }
}
