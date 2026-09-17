<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1788485938AddPromotionRedemptionIndexToOrderLineItem;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1788485938AddPromotionRedemptionIndexToOrderLineItem::class)]
class Migration1788485938AddPromotionRedemptionIndexToOrderLineItemTest extends TestCase
{
    private const INDEX_NAME = 'idx.order_line_item.promotion_redemption';

    private const FK_INDEX_NAME = 'fk.order_line_item.promotion_id';

    private const INDEX_COLUMNS = ['promotion_id', 'version_id', 'order_id', 'order_version_id'];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788485938, (new Migration1788485938AddPromotionRedemptionIndexToOrderLineItem())->getCreationTimestamp());
    }

    public function testMigrationCreatesExpectedIndexAndIsIdempotent(): void
    {
        $this->rollback();

        $migration = new Migration1788485938AddPromotionRedemptionIndexToOrderLineItem();
        $migration->update($this->connection);
        // Verify idempotency.
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'order_line_item', self::INDEX_NAME));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'order_line_item', self::INDEX_NAME, self::INDEX_COLUMNS));
    }

    public function testIndexSpansExactlyTheCoveringColumnsInOrder(): void
    {
        $this->rollback();

        (new Migration1788485938AddPromotionRedemptionIndexToOrderLineItem())->update($this->connection);

        // Verify the exact column set and order; indexSpansColumns allows trailing columns.
        $columns = $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index
             ORDER BY SEQ_IN_INDEX',
            ['table' => 'order_line_item', 'index' => self::INDEX_NAME]
        );

        static::assertSame(self::INDEX_COLUMNS, $columns);
    }

    public function testUpdateDestructiveDropsTheRedundantForeignKeyIndex(): void
    {
        $this->rollback();

        $migration = new Migration1788485938AddPromotionRedemptionIndexToOrderLineItem();
        $migration->update($this->connection);

        // Only a schema that carries the narrow index explicitly still has one to drop, so recreate
        // it where InnoDB retired its own.
        $this->createForeignKeyIndexIfMissing();

        $migration->updateDestructive($this->connection);
        // Verify idempotency.
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::indexExists($this->connection, 'order_line_item', self::FK_INDEX_NAME));
        static::assertTrue(TableHelper::indexExists($this->connection, 'order_line_item', self::INDEX_NAME));
        // The covering index leads with `promotion_id`, so the constraint survives without its own.
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'order_line_item', self::FK_INDEX_NAME));

        // Leave the covering index in place, as a migrated schema has it, and put the narrow index
        // back so the shared schema is not left short of what the other suites expect.
        $this->createForeignKeyIndexIfMissing();
    }

    private function rollback(): void
    {
        if (!TableHelper::indexExists($this->connection, 'order_line_item', self::INDEX_NAME)) {
            return;
        }

        // Where the constraint's own index was the one InnoDB created, creating the covering index
        // retires it, which leaves the covering index as the only support for the foreign key and
        // so undroppable. Put the narrow index back first.
        $this->createForeignKeyIndexIfMissing();

        $this->connection->executeStatement('DROP INDEX `' . self::INDEX_NAME . '` ON `order_line_item`');
    }

    private function createForeignKeyIndexIfMissing(): void
    {
        if (TableHelper::indexExists($this->connection, 'order_line_item', self::FK_INDEX_NAME)) {
            return;
        }

        $this->connection->executeStatement(
            'CREATE INDEX `' . self::FK_INDEX_NAME . '` ON `order_line_item` (`promotion_id`)'
        );
    }
}
