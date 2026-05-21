<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1773409342AppMcpPromptAndResource;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1773409342AppMcpPromptAndResource::class)]
class Migration1773409342AppMcpPromptAndResourceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_prompt_translation`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_prompt`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_resource_translation`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_resource`;');
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_mcp_prompt'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_mcp_prompt_translation'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_mcp_resource'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_mcp_resource_translation'));

        $migration = new Migration1773409342AppMcpPromptAndResource();
        static::assertSame(1773409342, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'app_mcp_prompt'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'app_mcp_prompt_translation'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'app_mcp_resource'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'app_mcp_resource_translation'));

        static::assertCount(6, TableHelper::getTable($this->connection, 'app_mcp_prompt')->columns);
        static::assertCount(6, TableHelper::getTable($this->connection, 'app_mcp_prompt_translation')->columns);
        static::assertCount(8, TableHelper::getTable($this->connection, 'app_mcp_resource')->columns);
        static::assertCount(6, TableHelper::getTable($this->connection, 'app_mcp_resource_translation')->columns);
    }
}
