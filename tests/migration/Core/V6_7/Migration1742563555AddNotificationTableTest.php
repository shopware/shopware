<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\DbTableHelper;
use Shopware\Core\Migration\V6_7\Migration1742563555AddNotificationTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1742563555AddNotificationTable::class)]
class Migration1742563555AddNotificationTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `notification`;');
    }

    public function testMigration(): void
    {
        static::assertFalse(DbTableHelper::tableExists($this->connection, 'notification'));

        $migration = new Migration1742563555AddNotificationTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(DbTableHelper::tableExists($this->connection, 'notification'));

        $messageColumn = DbTableHelper::getColumnOfTable($this->connection, 'notification', 'message');
        static::assertSame(Type::lookupName(Type::getType(Types::TEXT)), $messageColumn->type);
    }
}
