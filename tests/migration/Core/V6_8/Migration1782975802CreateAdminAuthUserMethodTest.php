<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1782975802CreateAdminAuthUserMethod;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1782975802CreateAdminAuthUserMethod::class)]
class Migration1782975802CreateAdminAuthUserMethodTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `admin_auth_user_method`;');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782975802, (new Migration1782975802CreateAdminAuthUserMethod())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'admin_auth_user_method'));

        $migration = new Migration1782975802CreateAdminAuthUserMethod();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'admin_auth_user_method'));

        static::assertCount(10, TableHelper::getTable($this->connection, 'admin_auth_user_method')->columns);
        foreach (['id', 'user_id', 'type', 'active', 'label', 'secret', 'credential', 'last_used_at', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'admin_auth_user_method', $column));
        }

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'admin_auth_user_method', 'fk.admin_auth_user_method.user_id'));
    }
}
