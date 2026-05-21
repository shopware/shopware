<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1779600015UcpEmbeddedSession;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600015UcpEmbeddedSession::class)]
class Migration1779600015UcpEmbeddedSessionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600015, (new Migration1779600015UcpEmbeddedSession())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_embedded_session'));

        $migration = new Migration1779600015UcpEmbeddedSession();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_embedded_session'));

        foreach ([
            'id',
            'session_token_hash',
            'sales_channel_id',
            'cart_id',
            'host_origin',
            'kind',
            'created_at',
            'expires_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_embedded_session', $column),
                \sprintf('Column "%s" is missing from ucp_embedded_session', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_embedded_session', 'uniq.ucp_embedded_session.token_hash'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_embedded_session', 'idx.ucp_embedded_session.cart_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_embedded_session', 'idx.ucp_embedded_session.expires_at'));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_embedded_session')) {
            $this->connection->executeStatement('DELETE FROM `ucp_embedded_session`');
        }
        $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_embedded_session`');
    }
}
