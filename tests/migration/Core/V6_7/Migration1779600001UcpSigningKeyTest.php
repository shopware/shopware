<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779600001UcpSigningKey;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600001UcpSigningKey::class)]
class Migration1779600001UcpSigningKeyTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600001, (new Migration1779600001UcpSigningKey())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_signing_key'));

        $migration = new Migration1779600001UcpSigningKey();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_signing_key'));

        foreach ([
            'id',
            'sales_channel_id',
            'kid',
            'algorithm',
            'public_jwk',
            'private_key_pem_encrypted',
            'status',
            'activated_at',
            'retiring_at',
            'created_at',
            'updated_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_signing_key', $column),
                \sprintf('Column "%s" is missing from ucp_signing_key', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_signing_key', 'uniq.ucp_signing_key.sc_kid'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_signing_key', 'idx.ucp_signing_key.status'));
        static::assertTrue(TableHelper::foreignKeyExistsByColumns(
            $this->connection,
            'ucp_signing_key',
            ['sales_channel_id'],
            'sales_channel',
            ['id']
        ));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_signing_key')) {
            $this->connection->executeStatement('DELETE FROM `ucp_signing_key`');
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_signing_key`');
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
