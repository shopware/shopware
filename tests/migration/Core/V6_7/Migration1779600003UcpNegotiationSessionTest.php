<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779600003UcpNegotiationSession;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600003UcpNegotiationSession::class)]
class Migration1779600003UcpNegotiationSessionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600003, (new Migration1779600003UcpNegotiationSession())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_negotiation_session'));

        $migration = new Migration1779600003UcpNegotiationSession();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_negotiation_session'));

        foreach ([
            'id',
            'sales_channel_id',
            'platform_profile_uri',
            'platform_profile_hash',
            'active_capabilities',
            'protocol_version',
            'last_used_at',
            'created_at',
            'updated_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_negotiation_session', $column),
                \sprintf('Column "%s" is missing from ucp_negotiation_session', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_negotiation_session', 'uniq.ucp_ns.sc_profile'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_negotiation_session', 'idx.ucp_ns.last_used_at'));
        static::assertTrue(TableHelper::foreignKeyExistsByColumns(
            $this->connection,
            'ucp_negotiation_session',
            ['sales_channel_id'],
            'sales_channel',
            ['id']
        ));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_negotiation_session')) {
            $this->connection->executeStatement('DELETE FROM `ucp_negotiation_session`');
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_negotiation_session`');
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
