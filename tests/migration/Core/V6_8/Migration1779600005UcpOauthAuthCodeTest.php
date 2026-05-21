<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1779600005UcpOauthAuthCode;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600005UcpOauthAuthCode::class)]
class Migration1779600005UcpOauthAuthCodeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600005, (new Migration1779600005UcpOauthAuthCode())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_oauth_auth_code'));

        $migration = new Migration1779600005UcpOauthAuthCode();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_oauth_auth_code'));

        foreach ([
            'identifier',
            'sales_channel_id',
            'client_id',
            'user_identifier',
            'scopes',
            'redirect_uri',
            'code_challenge',
            'code_challenge_method',
            'revoked',
            'expires_at',
            'created_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_oauth_auth_code', $column),
                \sprintf('Column "%s" is missing from ucp_oauth_auth_code', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_oauth_auth_code', 'idx.ucp_oauth_auth_code.expires_at'));
        static::assertTrue(TableHelper::foreignKeyExistsByColumns(
            $this->connection,
            'ucp_oauth_auth_code',
            ['sales_channel_id'],
            'sales_channel',
            ['id']
        ));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_oauth_auth_code')) {
            $this->connection->executeStatement('DELETE FROM `ucp_oauth_auth_code`');
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_oauth_auth_code`');
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
