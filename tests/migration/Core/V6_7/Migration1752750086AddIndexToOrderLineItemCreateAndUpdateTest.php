<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1752750086AddIndexToOrderLineItemCreateAndUpdate;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1752750086AddIndexToOrderLineItemCreateAndUpdate::class)]
class Migration1752750086AddIndexToOrderLineItemCreateAndUpdateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1752750086AddIndexToOrderLineItemCreateAndUpdate();
        static::assertSame(1752750086, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1752750086AddIndexToOrderLineItemCreateAndUpdate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $existingIndexes = $this->connection->createSchemaManager()->listTableIndexes('order_line_item');

        static::assertArrayHasKey('idx.order_line_item_created_updated', $existingIndexes);
    }

    private function rollback(): void
    {
        $existingIndexes = $this->connection->createSchemaManager()->listTableIndexes('order_line_item');

        if (isset($existingIndexes['idx.order_line_item_created_updated'])) {
            $this->connection->executeStatement('DROP INDEX `idx.order_line_item_created_updated` ON `order_line_item`');
        }
    }
}
