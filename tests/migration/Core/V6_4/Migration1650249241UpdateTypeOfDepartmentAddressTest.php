<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1650249241UpdateTypeOfDepartmentAddress;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1650249241UpdateTypeOfDepartmentAddress
 */
class Migration1650249241UpdateTypeOfDepartmentAddressTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        parent::setup();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1650249241UpdateTypeOfDepartmentAddress();
        $migration->update($connection);
    }

    public function testDepartmentColumnCustomerAddress(): void
    {
        $schema = $this->connection->getSchemaManager();

        $column = array_filter($schema->listTableColumns('customer_address'), static fn (Column $column): bool => $column->getName() === 'department');

        /** @var Column $department */
        $department = $column['department'];

        static::assertFalse($department->getNotnull());
        static::assertEquals($department->getLength(), 255);
        static::assertEquals($department->getPlatformOption('collation'), 'utf8mb4_unicode_ci');
    }

    public function testDepartmentColumnOrderAddress(): void
    {
        $schema = $this->connection->getSchemaManager();

        $column = array_filter($schema->listTableColumns('order_address'), static fn (Column $column): bool => $column->getName() === 'department');

        /** @var Column $department */
        $department = $column['department'];

        static::assertFalse($department->getNotnull());
        static::assertEquals($department->getLength(), 255);
        static::assertEquals($department->getPlatformOption('collation'), 'utf8mb4_unicode_ci');
    }
}
