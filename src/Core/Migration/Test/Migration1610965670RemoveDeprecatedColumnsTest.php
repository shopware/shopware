<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_3\Migration1610965670RemoveDeprecatedColumns;

class Migration1610965670RemoveDeprecatedColumnsTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var Connection|object|null
     */
    private $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        if ($this->getColumnInfo('product', 'purchase_price') === false) {
            $this->connection->executeUpdate('
                ALTER TABLE product
                ADD `purchase_price` double DEFAULT NULL
            ');
        }

        if ($this->getColumnInfo('currency', 'decimal_precision') === false) {
            $this->connection->executeUpdate('
                ALTER TABLE currency
                ADD `decimal_precision` int NOT NULL DEFAULT 2
            ');
        }

        if ($this->getColumnInfo('customer_wishlist_product', 'custom_fields') === false) {
            $this->connection->executeUpdate('
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
     * @return array|false
     */
    private function getColumnInfo(string $table, string $column)
    {
        $database = $this->connection->fetchColumn('SELECT DATABASE();');

        return $this->connection->fetchAssoc(
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
     * @return array|false
     */
    private function getTriggerInfo(string $triggerName)
    {
        $database = $this->connection->fetchColumn('SELECT DATABASE();');

        return $this->connection->fetchAssoc(
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
