<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779600016UcpSignatureNonce;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600016UcpSignatureNonce::class)]
class Migration1779600016UcpSignatureNonceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600016, (new Migration1779600016UcpSignatureNonce())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_signature_nonce'));

        $migration = new Migration1779600016UcpSignatureNonce();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_signature_nonce'));

        foreach ([
            'id',
            'sales_channel_id',
            'kid',
            'signature_hash',
            'created',
            'expires_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_signature_nonce', $column),
                \sprintf('Column "%s" is missing from ucp_signature_nonce', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_signature_nonce', 'uniq.ucp_signature_nonce.sc_kid_sig'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_signature_nonce', 'idx.ucp_signature_nonce.expires_at'));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_signature_nonce')) {
            $this->connection->executeStatement('DELETE FROM `ucp_signature_nonce`');
        }
        $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_signature_nonce`');
    }
}
