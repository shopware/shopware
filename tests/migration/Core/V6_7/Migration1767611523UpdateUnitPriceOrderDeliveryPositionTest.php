<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\FloatType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1767611523UpdateUnitPriceOrderDeliveryPosition;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1767611523UpdateUnitPriceOrderDeliveryPosition::class)]
class Migration1767611523UpdateUnitPriceOrderDeliveryPositionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdate(): void
    {
        $this->rollBack();

        $migration = new Migration1767611523UpdateUnitPriceOrderDeliveryPosition();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $type = $this->connection->createSchemaManager()->introspectTable('order_delivery_position')->getColumn('unit_price')->getType();

        static::assertInstanceOf(FloatType::class, $type);
    }

    private function rollBack(): void
    {
        $this->connection->executeStatement('
            ALTER TABLE `order_delivery_position`
            MODIFY `unit_price` INT(11)
            GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, \'$.unit_price\'))) VIRTUAL
        ');
    }
}
