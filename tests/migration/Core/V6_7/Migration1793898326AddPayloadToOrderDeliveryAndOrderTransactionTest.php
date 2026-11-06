<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1793898326AddPayloadToOrderDeliveryAndOrderTransaction;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1793898326AddPayloadToOrderDeliveryAndOrderTransaction::class)]
class Migration1793898326AddPayloadToOrderDeliveryAndOrderTransactionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793898326, (new Migration1793898326AddPayloadToOrderDeliveryAndOrderTransaction())->getCreationTimestamp());
    }

    public function testUpdateAddsThePayloadColumnsAndIsRepeatable(): void
    {
        foreach (['order_delivery', 'order_transaction'] as $table) {
            if (TableHelper::columnExists($this->connection, $table, 'payload')) {
                $this->connection->executeStatement(\sprintf('ALTER TABLE `%s` DROP COLUMN `payload`', $table));
            }
        }

        $migration = new Migration1793898326AddPayloadToOrderDeliveryAndOrderTransaction();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'order_delivery', 'payload'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'order_transaction', 'payload'));
    }
}
