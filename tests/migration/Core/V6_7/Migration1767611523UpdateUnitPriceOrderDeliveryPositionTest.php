<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\FloatType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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

    private string $deliveryId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->deliveryId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $deletedRowCount = $this->connection->executeStatement(
            'DELETE FROM `order_delivery_position` WHERE id = :id',
            ['id' => $this->deliveryId]
        );

        static::assertSame(1, (int) $deletedRowCount);
    }

    public function testUpdate(): void
    {
        $this->rollBack();

        $this->connection->executeStatement('SET foreign_key_checks = 0');

        $this->createDeliveryPosition();

        $migration = new Migration1767611523UpdateUnitPriceOrderDeliveryPosition();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $type = $this->connection
            ->createSchemaManager()
            ->introspectTable('order_delivery_position')
            ->getColumn('unit_price')
            ->getType();

        $value = $this->connection->fetchOne(
            'SELECT unit_price FROM order_delivery_position WHERE id = :id',
            ['id' => $this->deliveryId]
        );

        $this->connection->executeStatement('SET foreign_key_checks = 1');

        static::assertSame(12.12, (float) $value);
        static::assertInstanceOf(FloatType::class, $type);
    }

    private function rollBack(): void
    {
        $this->connection->executeStatement('
            ALTER TABLE `order_delivery_position`
            MODIFY `unit_price` INT(11)
            GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, \'$.unitPrice\'))) VIRTUAL
        ');
    }

    private function createDeliveryPosition(): void
    {
        $this->connection->insert('order_delivery_position', [
            'id' => $this->deliveryId,
            'version_id' => Uuid::randomBytes(),
            'order_delivery_id' => Uuid::randomBytes(),
            'order_delivery_version_id' => Uuid::randomBytes(),
            'order_line_item_id' => Uuid::randomBytes(),
            'order_line_item_version_id' => Uuid::randomBytes(),
            'price' => '{"quantity": 1, "unitPrice": 12.12, "totalPrice": 12.12}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
