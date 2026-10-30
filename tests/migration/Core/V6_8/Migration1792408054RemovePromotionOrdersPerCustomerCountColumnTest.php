<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1792408054RemovePromotionOrdersPerCustomerCountColumn;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1792408054RemovePromotionOrdersPerCustomerCountColumn::class)]
class Migration1792408054RemovePromotionOrdersPerCustomerCountColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792408054, (new Migration1792408054RemovePromotionOrdersPerCustomerCountColumn())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesOrdersPerCustomerCountColumn(): void
    {
        $this->ensureOrdersPerCustomerCountColumnExists();

        $migration = new Migration1792408054RemovePromotionOrdersPerCustomerCountColumn();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'promotion', 'orders_per_customer_count'));
    }

    private function ensureOrdersPerCustomerCountColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'promotion', 'orders_per_customer_count')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `promotion` ADD COLUMN `orders_per_customer_count` JSON NULL');
    }
}
