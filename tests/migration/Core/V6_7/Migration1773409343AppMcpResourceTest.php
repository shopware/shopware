<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1773409343AppMcpResource;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1773409343AppMcpResource::class)]
class Migration1773409343AppMcpResourceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_resource_translation`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_resource`;');
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_mcp_resource'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_mcp_resource_translation'));

        $migration = new Migration1773409343AppMcpResource();
        static::assertSame(1773409343, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'app_mcp_resource'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'app_mcp_resource_translation'));

        static::assertCount(8, TableHelper::getTable($this->connection, 'app_mcp_resource')->columns);
        static::assertCount(6, TableHelper::getTable($this->connection, 'app_mcp_resource_translation')->columns);
    }
}
