<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779600007UcpOauthRefreshToken;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600007UcpOauthRefreshToken::class)]
class Migration1779600007UcpOauthRefreshTokenTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600007, (new Migration1779600007UcpOauthRefreshToken())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_oauth_refresh_token'));

        $migration = new Migration1779600007UcpOauthRefreshToken();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_oauth_refresh_token'));

        foreach ([
            'identifier',
            'access_token_identifier',
            'revoked',
            'expires_at',
            'created_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_oauth_refresh_token', $column),
                \sprintf('Column "%s" is missing from ucp_oauth_refresh_token', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_oauth_refresh_token', 'idx.ucp_oauth_refresh_token.access_token'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_oauth_refresh_token', 'idx.ucp_oauth_refresh_token.expires_at'));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_oauth_refresh_token')) {
            $this->connection->executeStatement('DELETE FROM `ucp_oauth_refresh_token`');
        }
        $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_oauth_refresh_token`');
    }
}
