<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1610965670RemoveDeprecatedColumns;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1610965670RemoveDeprecatedColumns
 */
class Migration1610965670RemoveDeprecatedColumnsTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        if ($this->getColumnInfo('product', 'purchase_price') === false) {
            $this->connection->executeStatement('
                ALTER TABLE product
                ADD `purchase_price` double DEFAULT NULL
            ');
        }

        if ($this->getColumnInfo('currency', 'decimal_precision') === false) {
            $this->connection->executeStatement('
                ALTER TABLE currency
                ADD `decimal_precision` int NOT NULL DEFAULT 2
            ');
        }

        if ($this->getColumnInfo('customer_wishlist_product', 'custom_fields') === false) {
            $this->connection->executeStatement('
                ALTER TABLE customer_wishlist_product
                ADD `custom_fields` JSON NULL
            ');
        }
    }

    public function testColumnDoesNotExist(): void
    {
        $migration = new Migration1610965670RemoveDeprecatedColumns();
        $migration->updateDestructive($this->connection);

        // can execute two times
        $migration->updateDestructive($this->connection);

        $this->assertColumnNotExists('currency', 'decimal_precision');
        $this->assertTriggerNotExists('currency_cash_rounding_insert');
        $this->assertTriggerNotExists('currency_cash_rounding_update');

        $this->assertColumnNotExists('product', 'purchase_price');
        $this->assertColumnExists('product', 'purchase_prices');
        $this->assertTriggerNotExists('product_purchase_prices_insert');
        $this->assertTriggerNotExists('product_purchase_prices_update');

        $this->assertColumnNotExists('customer_wishlist_product', 'custom_fields');
    }

    private function assertColumnExists(string $table, string $column): void
    {
        $exists = $this->getColumnInfo($table, $column);

        static::assertNotFalse($exists, 'Failed asserting that column `' . $table . '`.`' . $column . '` does exist');
    }

    private function assertColumnNotExists(string $table, string $column): void
    {
        $exists = $this->getColumnInfo($table, $column);

        static::assertFalse($exists, 'Failed asserting that column `' . $table . '`.`' . $column . '` does not exist');
    }

    private function assertTriggerNotExists(string $triggerName): void
    {
        $exists = $this->getTriggerInfo($triggerName);

        static::assertFalse($exists, 'Failed asserting that trigger `' . $triggerName . '` does not exist');
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getColumnInfo(string $table, string $column): array|false
    {
        $database = $this->connection->fetchOne('SELECT DATABASE();');

        return $this->connection->fetchAssociative(
            '
                SELECT * FROM information_schema.`COLUMNS`
                WHERE TABLE_NAME = :table
                AND TABLE_SCHEMA = :database
                AND COLUMN_NAME = :column',
            [
                'table' => $table,
                'database' => $database,
                'column' => $column,
            ]
        );
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getTriggerInfo(string $triggerName): array|false
    {
        $database = $this->connection->fetchOne('SELECT DATABASE();');

        return $this->connection->fetchAssociative(
            '
                SELECT * FROM information_schema.`TRIGGERS`
                WHERE TRIGGER_SCHEMA = :database
                AND TRIGGER_NAME = :trigger',
            [
                'database' => $database,
                'trigger' => $triggerName,
            ]
        );
    }
}
