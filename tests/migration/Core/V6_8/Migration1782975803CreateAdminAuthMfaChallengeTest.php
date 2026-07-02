<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1782975803CreateAdminAuthMfaChallenge;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1782975803CreateAdminAuthMfaChallenge::class)]
class Migration1782975803CreateAdminAuthMfaChallengeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `admin_auth_mfa_challenge`;');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782975803, (new Migration1782975803CreateAdminAuthMfaChallenge())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'admin_auth_mfa_challenge'));

        $migration = new Migration1782975803CreateAdminAuthMfaChallenge();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'admin_auth_mfa_challenge'));

        static::assertCount(9, TableHelper::getTable($this->connection, 'admin_auth_mfa_challenge')->columns);
        foreach (['id', 'user_id', 'pending_jti', 'webauthn_challenge', 'allowed_methods', 'attempts', 'consumed', 'expires_at', 'created_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'admin_auth_mfa_challenge', $column));
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'admin_auth_mfa_challenge', 'uniq.admin_auth_mfa_challenge.pending_jti'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'admin_auth_mfa_challenge', 'idx.admin_auth_mfa_challenge.expires'));
    }
}
