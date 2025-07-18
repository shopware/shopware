<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1752823743ShippingMethodPriceQuantityStepColumns;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1752823743ShippingMethodPriceQuantityStepColumns::class)]
class Migration1752823743ShippingMethodPriceQuantityStepColumnsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $this->revertMigration($connection);

        $migration = new Migration1752823743ShippingMethodPriceQuantityStepColumns();
        $migration->update($connection);
        $migration->update($connection);

        $manager = $connection->createSchemaManager();
        $columns = $manager->listTableColumns('shipping_method_price');

        static::assertArrayHasKey('quantity_step', $columns);
        static::assertFalse($columns['quantity_step']->getNotnull());

        static::assertArrayHasKey('quantity_step_price', $columns);
        static::assertFalse($columns['quantity_step_price']->getNotnull());
    }

    private function revertMigration(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `shipping_method_price` DROP COLUMN `quantity_step`');
        $connection->executeStatement('ALTER TABLE `shipping_method_price` DROP COLUMN `quantity_step_price`');
    }
}
