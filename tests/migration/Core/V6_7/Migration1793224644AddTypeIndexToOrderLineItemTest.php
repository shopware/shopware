<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1793224644AddTypeIndexToOrderLineItem;
use Shopware\Tests\Migration\NonStandardFkGuardMigrationTestCase;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1793224644AddTypeIndexToOrderLineItem::class)]
class Migration1793224644AddTypeIndexToOrderLineItemTest extends NonStandardFkGuardMigrationTestCase
{
    private const INDEX_NAME = 'idx.order_line_item.type';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793224644, (new Migration1793224644AddTypeIndexToOrderLineItem())->getCreationTimestamp());
    }

    public function testMigrationCreatesExpectedIndexAndIsIdempotent(): void
    {
        $this->rollback();

        $migration = new Migration1793224644AddTypeIndexToOrderLineItem();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index
             ORDER BY SEQ_IN_INDEX',
            ['table' => 'order_line_item', 'index' => self::INDEX_NAME]
        );

        static::assertSame(['type'], $columns);
    }

    public function testIndexCreationSurvivesNonStandardForeignKeyGuard(): void
    {
        $this->assertIndexCreationSurvivesNonStandardForeignKeyGuard(new Migration1793224644AddTypeIndexToOrderLineItem(), 'order_line_item');
    }

    private function rollback(): void
    {
        if (TableHelper::indexExists($this->connection, 'order_line_item', self::INDEX_NAME)) {
            $this->connection->executeStatement('DROP INDEX `' . self::INDEX_NAME . '` ON `order_line_item`');
        }
    }
}
