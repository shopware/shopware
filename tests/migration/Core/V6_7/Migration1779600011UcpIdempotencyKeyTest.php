<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779600011UcpIdempotencyKey;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600011UcpIdempotencyKey::class)]
class Migration1779600011UcpIdempotencyKeyTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600011, (new Migration1779600011UcpIdempotencyKey())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_idempotency_key'));

        $migration = new Migration1779600011UcpIdempotencyKey();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_idempotency_key'));

        foreach ([
            'id',
            'sales_channel_id',
            'idempotency_key',
            'route_name',
            'request_fingerprint',
            'response_status',
            'response_headers',
            'response_body',
            'created_at',
            'expires_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_idempotency_key', $column),
                \sprintf('Column "%s" is missing from ucp_idempotency_key', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_idempotency_key', 'uniq.ucp_idempotency_key.sc_key'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_idempotency_key', 'idx.ucp_idempotency_key.expires_at'));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_idempotency_key')) {
            $this->connection->executeStatement('DELETE FROM `ucp_idempotency_key`');
        }
        $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_idempotency_key`');
    }
}
