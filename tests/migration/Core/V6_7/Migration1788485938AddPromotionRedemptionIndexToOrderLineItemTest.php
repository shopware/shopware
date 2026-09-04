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

    private const INDEX_COLUMNS = ['promotion_id', 'version_id', 'type', 'order_id', 'order_version_id'];

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

    private function rollback(): void
    {
        if (!TableHelper::indexExists($this->connection, 'order_line_item', self::INDEX_NAME)) {
            return;
        }

        // Restore the FK index first if InnoDB retired it when creating the covering index.
        if (!TableHelper::indexExists($this->connection, 'order_line_item', self::FK_INDEX_NAME)) {
            $this->connection->executeStatement(
                'CREATE INDEX `' . self::FK_INDEX_NAME . '` ON `order_line_item` (`promotion_id`)'
            );
        }

        $this->connection->executeStatement('DROP INDEX `' . self::INDEX_NAME . '` ON `order_line_item`');
    }
}
