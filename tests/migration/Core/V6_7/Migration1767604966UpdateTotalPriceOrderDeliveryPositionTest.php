<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1767604966UpdateTotalPriceOrderDeliveryPosition;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1767604966UpdateTotalPriceOrderDeliveryPosition::class)]
class Migration1767604966UpdateTotalPriceOrderDeliveryPositionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private string $deliveryId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->deliveryId = Uuid::randomBytes();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1767604966, (new Migration1767604966UpdateTotalPriceOrderDeliveryPosition())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->rollBack();

        $this->connection->executeStatement('SET foreign_key_checks = 0');

        $this->createDeliveryPosition();

        try {
            $migration = new Migration1767604966UpdateTotalPriceOrderDeliveryPosition();

            $migration->update($this->connection);
            $migration->update($this->connection);

            $value = (float) $this->connection->fetchOne(
                'SELECT total_price FROM order_delivery_position WHERE id = :id',
                ['id' => $this->deliveryId]
            );

            static::assertSame(12.12, $value);
            $type = TableHelper::getColumnOfTable($this->connection, 'order_delivery_position', 'total_price')->type;
            static::assertSame(Types::FLOAT, $type);
        } finally {
            $this->connection->executeStatement('SET foreign_key_checks = 1');
            $deletedRowCount = $this->connection->executeStatement(
                'DELETE FROM `order_delivery_position` WHERE id = :id',
                ['id' => $this->deliveryId]
            );
        }

        static::assertSame(1, (int) $deletedRowCount);
    }

    private function rollBack(): void
    {
        $this->connection->executeStatement('
            ALTER TABLE `order_delivery_position`
            MODIFY `total_price` INT(11)
            GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, \'$.totalPrice\'))) VIRTUAL
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
