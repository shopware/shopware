<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779600006UcpOauthAccessToken;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600006UcpOauthAccessToken::class)]
class Migration1779600006UcpOauthAccessTokenTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600006, (new Migration1779600006UcpOauthAccessToken())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_oauth_access_token'));

        $migration = new Migration1779600006UcpOauthAccessToken();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_oauth_access_token'));

        foreach ([
            'identifier',
            'sales_channel_id',
            'client_id',
            'user_identifier',
            'scopes',
            'revoked',
            'expires_at',
            'created_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_oauth_access_token', $column),
                \sprintf('Column "%s" is missing from ucp_oauth_access_token', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_oauth_access_token', 'idx.ucp_oauth_access_token.expires_at'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_oauth_access_token', 'idx.ucp_oauth_access_token.user'));
        static::assertTrue(TableHelper::foreignKeyExistsByColumns(
            $this->connection,
            'ucp_oauth_access_token',
            ['sales_channel_id'],
            'sales_channel',
            ['id']
        ));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_oauth_access_token')) {
            $this->connection->executeStatement('DELETE FROM `ucp_oauth_access_token`');
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_oauth_access_token`');
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
