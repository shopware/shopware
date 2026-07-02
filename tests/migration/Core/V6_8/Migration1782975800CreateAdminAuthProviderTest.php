<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1782975800CreateAdminAuthProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1782975800CreateAdminAuthProvider::class)]
class Migration1782975800CreateAdminAuthProviderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `admin_auth_provider`;');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782975800, (new Migration1782975800CreateAdminAuthProvider())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'admin_auth_provider'));

        $migration = new Migration1782975800CreateAdminAuthProvider();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'admin_auth_provider'));

        static::assertCount(10, TableHelper::getTable($this->connection, 'admin_auth_provider')->columns);
        foreach (['id', 'name', 'type', 'active', 'is_primary', 'is_second_factor', 'priority', 'config', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'admin_auth_provider', $column));
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'admin_auth_provider', 'idx.admin_auth_provider.lookup'));
    }
}
