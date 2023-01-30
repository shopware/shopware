<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1648031636AddPositionFieldToShippingMethod;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1648031636AddPositionFieldToShippingMethod
 */
class Migration1648031636AddPositionFieldToShippingMethodTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1648031636AddPositionFieldToShippingMethod();
        $resultColumnExists = $this->hasColumn('shipping_method', 'position');
        static::assertFalse($resultColumnExists);

        $migration->update($this->connection);
        $migration->update($this->connection);

        $resultColumnExists = $this->hasColumn('shipping_method', 'position');
        static::assertTrue($resultColumnExists);
    }

    private function prepare(): void
    {
        $resultColumnExists = $this->hasColumn('shipping_method', 'position');

        if ($resultColumnExists) {
            $this->connection->executeStatement('ALTER TABLE `shipping_method` DROP COLUMN `position`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \count(array_filter(
            $this->connection->getSchemaManager()->listTableColumns($table),
            static fn (Column $column): bool => $column->getName() === $columnName
        )) > 0;
    }
}
