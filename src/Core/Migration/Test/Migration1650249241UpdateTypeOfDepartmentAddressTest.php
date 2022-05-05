<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1650249241UpdateTypeOfDepartmentAddress;

/**
 * @internal
 */
class Migration1650249241UpdateTypeOfDepartmentAddressTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        parent::setup();

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $migration = new Migration1650249241UpdateTypeOfDepartmentAddress();
        $migration->update($connection);
    }

    public function testDepartmentColumnCustomerAddress(): void
    {
        $schema = $this->connection->getSchemaManager();

        $column = array_filter($schema->listTableColumns('customer_address'), static function (Column $column): bool {
            return $column->getName() === 'department';
        });

        /** @var Column $department */
        $department = $column['department'];

        static::assertFalse($department->getNotnull());
        static::assertEquals($department->getLength(), 255);
        static::assertEquals($department->getPlatformOption('collation'), 'utf8mb4_unicode_ci');
    }

    public function testDepartmentColumnOrderAddress(): void
    {
        $schema = $this->connection->getSchemaManager();

        $column = array_filter($schema->listTableColumns('order_address'), static function (Column $column): bool {
            return $column->getName() === 'department';
        });

        /** @var Column $department */
        $department = $column['department'];

        static::assertFalse($department->getNotnull());
        static::assertEquals($department->getLength(), 255);
        static::assertEquals($department->getPlatformOption('collation'), 'utf8mb4_unicode_ci');
    }
}
