<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1782975804CreateAdminAuthRoleAssignment;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1782975804CreateAdminAuthRoleAssignment::class)]
class Migration1782975804CreateAdminAuthRoleAssignmentTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `admin_auth_role_assignment`;');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782975804, (new Migration1782975804CreateAdminAuthRoleAssignment())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'admin_auth_role_assignment'));

        $migration = new Migration1782975804CreateAdminAuthRoleAssignment();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'admin_auth_role_assignment'));

        static::assertCount(6, TableHelper::getTable($this->connection, 'admin_auth_role_assignment')->columns);
        foreach (['id', 'user_id', 'provider_key', 'acl_role_id', 'is_admin_grant', 'created_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'admin_auth_role_assignment', $column));
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'admin_auth_role_assignment', 'uniq.admin_auth_role_assignment.user_provider_role'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'admin_auth_role_assignment', 'fk.admin_auth_role_assignment.user_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'admin_auth_role_assignment', 'fk.admin_auth_role_assignment.acl_role_id'));
    }
}
