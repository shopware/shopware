<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1782975801CreateAdminAuthOauthIdentity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1782975801CreateAdminAuthOauthIdentity::class)]
class Migration1782975801CreateAdminAuthOauthIdentityTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `admin_auth_oauth_identity`;');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782975801, (new Migration1782975801CreateAdminAuthOauthIdentity())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'admin_auth_oauth_identity'));

        $migration = new Migration1782975801CreateAdminAuthOauthIdentity();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'admin_auth_oauth_identity'));

        static::assertCount(7, TableHelper::getTable($this->connection, 'admin_auth_oauth_identity')->columns);
        foreach (['id', 'provider_id', 'user_id', 'sub', 'email', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'admin_auth_oauth_identity', $column));
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'admin_auth_oauth_identity', 'uniq.admin_auth_oauth_identity.provider_sub'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'admin_auth_oauth_identity', 'fk.admin_auth_oauth_identity.user_id'));

        // there must be no foreign key on provider_id, YAML-declared providers have no database row
        static::assertFalse(TableHelper::foreignKeyExistsByColumns($this->connection, 'admin_auth_oauth_identity', ['provider_id'], 'admin_auth_provider', ['id']));
    }
}
