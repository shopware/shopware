<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1779600004UcpOauthClient;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600004UcpOauthClient::class)]
class Migration1779600004UcpOauthClientTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600004, (new Migration1779600004UcpOauthClient())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_oauth_client'));

        $migration = new Migration1779600004UcpOauthClient();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_oauth_client'));

        foreach ([
            'id',
            'sales_channel_id',
            'client_id',
            'name',
            'redirect_uris',
            'is_confidential',
            'client_secret_hash',
            'allowed_scopes',
            'platform_profile_uri',
            'created_at',
            'updated_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_oauth_client', $column),
                \sprintf('Column "%s" is missing from ucp_oauth_client', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_oauth_client', 'uniq.ucp_oauth_client.sc_client'));
        static::assertTrue(TableHelper::foreignKeyExistsByColumns(
            $this->connection,
            'ucp_oauth_client',
            ['sales_channel_id'],
            'sales_channel',
            ['id']
        ));
    }

    private function rollback(): void
    {
        // The follow-up migration Migration1779600013 adds further columns and a child table; clear that first.
        if (TableHelper::tableExists($this->connection, 'ucp_oauth_client_assertion')) {
            $this->connection->executeStatement('DELETE FROM `ucp_oauth_client_assertion`');
        }
        if (TableHelper::tableExists($this->connection, 'ucp_oauth_client')) {
            $this->connection->executeStatement('DELETE FROM `ucp_oauth_client`');
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_oauth_client_assertion`');
            $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_oauth_client`');
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
