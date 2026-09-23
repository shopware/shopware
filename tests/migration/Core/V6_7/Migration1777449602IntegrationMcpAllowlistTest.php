<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1777449602IntegrationMcpAllowlist;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1777449602IntegrationMcpAllowlist::class)]
class Migration1777449602IntegrationMcpAllowlistTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1777449602, (new Migration1777449602IntegrationMcpAllowlist())->getCreationTimestamp());
    }

    public function testUpdateAddsTheAllowlistColumnOnce(): void
    {
        if (TableHelper::columnExists($this->connection, 'integration', 'mcp_allowlist')) {
            $this->connection->executeStatement('ALTER TABLE `integration` DROP COLUMN `mcp_allowlist`');
        }

        $migration = new Migration1777449602IntegrationMcpAllowlist();

        $migration->update($this->connection);
        static::assertTrue(TableHelper::columnExists($this->connection, 'integration', 'mcp_allowlist'));

        $migration->update($this->connection);
        static::assertTrue(TableHelper::columnExists($this->connection, 'integration', 'mcp_allowlist'));
    }
}
