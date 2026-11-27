<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1794656488AddIdentifierToOrderDeliveryAndOrderTransaction;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1794656488AddIdentifierToOrderDeliveryAndOrderTransaction::class)]
class Migration1794656488AddIdentifierToOrderDeliveryAndOrderTransactionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794656488, (new Migration1794656488AddIdentifierToOrderDeliveryAndOrderTransaction())->getCreationTimestamp());
    }

    public function testUpdateAddsNullableIdentifierColumnsAndIsRepeatable(): void
    {
        foreach (['order_delivery', 'order_transaction'] as $table) {
            if (TableHelper::columnExists($this->connection, $table, 'identifier')) {
                $this->connection->executeStatement(\sprintf('ALTER TABLE `%s` DROP COLUMN `identifier`', $table));
            }
        }

        $migration = new Migration1794656488AddIdentifierToOrderDeliveryAndOrderTransaction();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['order_delivery', 'order_transaction'] as $table) {
            $column = TableHelper::getColumnOfTable($this->connection, $table, 'identifier');
            static::assertFalse($column->isNotNull, $table);
            static::assertNull($column->defaultValue, $table);
        }
    }
}
