<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1787136514AddPriceBasisToCustomerGroup;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1787136514AddPriceBasisToCustomerGroup::class)]
class Migration1787136514AddPriceBasisToCustomerGroupTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationAddsNullableColumnWithoutBackfill(): void
    {
        $this->rollback();

        static::assertFalse($this->columnExists());

        $customerGroupCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `customer_group`');
        static::assertGreaterThan(0, $customerGroupCount);

        $migration = new Migration1787136514AddPriceBasisToCustomerGroup();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(CustomerGroupDefinition::ENTITY_NAME)
            ->getColumn('price_basis');

        static::assertFalse($column->getNotnull());
        static::assertSame(
            0,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `customer_group` WHERE `price_basis` IS NOT NULL')
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1787136514,
            (new Migration1787136514AddPriceBasisToCustomerGroup())->getCreationTimestamp()
        );
    }

    private function columnExists(): bool
    {
        return TableHelper::columnExists($this->connection, CustomerGroupDefinition::ENTITY_NAME, 'price_basis');
    }

    private function rollback(): void
    {
        if (!$this->columnExists()) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `customer_group` DROP COLUMN `price_basis`');
    }
}
