<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1639139581AddPriorityToPromotions;

class Migration1639139581AddPriorityToPromotionsTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
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
