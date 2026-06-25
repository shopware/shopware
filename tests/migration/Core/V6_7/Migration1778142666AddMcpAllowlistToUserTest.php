<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1778142666AddMcpAllowlistToUser;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1778142666AddMcpAllowlistToUser::class)]
class Migration1778142666AddMcpAllowlistToUserTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        if (TableHelper::columnExists($this->connection, 'user', 'mcp_allowlist')) {
            $this->connection->executeStatement('ALTER TABLE `user` DROP COLUMN `mcp_allowlist`;');
        }
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::columnExists($this->connection, 'user', 'mcp_allowlist'));

        $migration = new Migration1778142666AddMcpAllowlistToUser();
        static::assertSame(1778142666, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'user', 'mcp_allowlist'));

        $column = TableHelper::getColumnOfTable($this->connection, 'user', 'mcp_allowlist');
        static::assertFalse($column->isNotNull);
        static::assertSame('json', $column->type);
    }

    public function testUpdateDestructiveIsNoop(): void
    {
        $migration = new Migration1778142666AddMcpAllowlistToUser();
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'user', 'mcp_allowlist'));
    }
}
