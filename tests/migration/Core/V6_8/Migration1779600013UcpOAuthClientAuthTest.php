<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1779600004UcpOauthClient;
use Shopware\Core\Migration\V6_8\Migration1779600013UcpOAuthClientAuth;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600013UcpOAuthClientAuth::class)]
class Migration1779600013UcpOAuthClientAuthTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600013, (new Migration1779600013UcpOAuthClientAuth())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        // The migration adds columns to ucp_oauth_client and creates ucp_oauth_client_assertion.
        // Ensure the base table exists, then strip what this migration is supposed to install.
        (new Migration1779600004UcpOauthClient())->update($this->connection);
        $this->rollback();

        static::assertFalse(TableHelper::columnExists($this->connection, 'ucp_oauth_client', 'jwks_json'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'ucp_oauth_client', 'tls_client_auth_subject_dn'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_oauth_client_assertion'));

        $migration = new Migration1779600013UcpOAuthClientAuth();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'ucp_oauth_client', 'jwks_json'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'ucp_oauth_client', 'tls_client_auth_subject_dn'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_oauth_client_assertion'));

        foreach ([
            'id',
            'sales_channel_id',
            'iss',
            'jti',
            'expires_at',
            'created_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_oauth_client_assertion', $column),
                \sprintf('Column "%s" is missing from ucp_oauth_client_assertion', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_oauth_client_assertion', 'uniq.ucp_oauth_client_assertion.sc_iss_jti'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_oauth_client_assertion', 'idx.ucp_oauth_client_assertion.expires_at'));
    }

    private function rollback(): void
    {
        foreach (['jwks_json', 'tls_client_auth_subject_dn'] as $column) {
            if (TableHelper::columnExists($this->connection, 'ucp_oauth_client', $column)) {
                $this->connection->executeStatement(\sprintf(
                    'ALTER TABLE `ucp_oauth_client` DROP COLUMN `%s`',
                    $column,
                ));
            }
        }

        if (TableHelper::tableExists($this->connection, 'ucp_oauth_client_assertion')) {
            $this->connection->executeStatement('DELETE FROM `ucp_oauth_client_assertion`');
        }
        $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_oauth_client_assertion`');
    }
}
