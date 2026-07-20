<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1763125903RemoveOrderLineItemStatesColumn;

/**
 * @internal
 */
#[CoversClass(Migration1763125903RemoveOrderLineItemStatesColumn::class)]
class Migration1763125903RemoveOrderLineItemStatesColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763125903, (new Migration1763125903RemoveOrderLineItemStatesColumn())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesStatesColumn(): void
    {
        $this->ensureStatesColumnExists();

        $migration = new Migration1763125903RemoveOrderLineItemStatesColumn();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'order_line_item', 'states'));
    }

    private function ensureStatesColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'order_line_item', 'states')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `order_line_item` ADD COLUMN `states` JSON NULL');
        $this->connection->executeStatement('ALTER TABLE `order_line_item` ADD CONSTRAINT `json.order_line_item.states` CHECK (JSON_VALID(`states`))');
    }
}
