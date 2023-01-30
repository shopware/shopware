<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1667806582AddCreatedByIdAndUpdatedByIdToCustomer;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1667806582AddCreatedByIdAndUpdatedByIdToCustomer
 */
class Migration1667806582AddCreatedByIdAndUpdatedByIdToCustomerTest extends TestCase
{
    private Connection $connection;

    private Migration1667806582AddCreatedByIdAndUpdatedByIdToCustomer $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->migration = new Migration1667806582AddCreatedByIdAndUpdatedByIdToCustomer();
    }

    public function testMigrationColumn(): void
    {
        $this->removeColumn();
        static::assertFalse($this->hasColumn('customer', 'created_by_id'));
        static::assertFalse($this->hasColumn('customer', 'updated_by_id'));

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertTrue($this->hasColumn('customer', 'created_by_id'));
        static::assertTrue($this->hasColumn('customer', 'updated_by_id'));
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals(1667806582, $this->migration->getCreationTimestamp());
    }

    private function removeColumn(): void
    {
        if ($this->hasColumn('customer', 'created_by_id')) {
            $this->connection->executeStatement('ALTER TABLE `customer` DROP FOREIGN KEY `fk.customer.created_by_id`');
            $this->connection->executeStatement('ALTER TABLE `customer` DROP COLUMN `created_by_id`');
        }

        if ($this->hasColumn('customer', 'updated_by_id')) {
            $this->connection->executeStatement('ALTER TABLE `customer` DROP FOREIGN KEY `fk.customer.updated_by_id`');
            $this->connection->executeStatement('ALTER TABLE `customer` DROP COLUMN `updated_by_id`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \in_array($columnName, array_column($this->connection->fetchAllAssociative(\sprintf('SHOW COLUMNS FROM `%s`', $table)), 'Field'), true);
    }
}
