<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1639139581AddPriorityToPromotions;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1639139581AddPriorityToPromotions
 */
class Migration1639139581AddPriorityToPromotionsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationCanExecuteMultipleTimes(): void
    {
        // Rollback Migration
        $this->connection->executeStatement('ALTER TABLE `promotion` DROP COLUMN `priority`');

        $migration = new Migration1639139581AddPriorityToPromotions();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $promotionColumns = $this->connection->getSchemaManager()->listTableColumns('promotion');
        static::assertArrayHasKey('priority', $promotionColumns);
    }
}
