<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779600012UcpBuyerConsent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600012UcpBuyerConsent::class)]
class Migration1779600012UcpBuyerConsentTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600012, (new Migration1779600012UcpBuyerConsent())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_buyer_consent'));

        $migration = new Migration1779600012UcpBuyerConsent();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_buyer_consent'));

        foreach ([
            'id',
            'sales_channel_id',
            'checkout_id',
            'consent_json',
            'created_at',
            'updated_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_buyer_consent', $column),
                \sprintf('Column "%s" is missing from ucp_buyer_consent', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_buyer_consent', 'uniq.ucp_buyer_consent.checkout_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_buyer_consent', 'idx.ucp_buyer_consent.sales_channel_id'));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_buyer_consent')) {
            $this->connection->executeStatement('DELETE FROM `ucp_buyer_consent`');
        }
        $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_buyer_consent`');
    }
}
