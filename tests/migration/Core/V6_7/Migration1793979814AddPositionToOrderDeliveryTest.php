<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1793979814AddPositionToOrderDelivery;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1793979814AddPositionToOrderDelivery::class)]
class Migration1793979814AddPositionToOrderDeliveryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793979814, (new Migration1793979814AddPositionToOrderDelivery())->getCreationTimestamp());
    }

    public function testUpdateAddsANotNullPositionColumnAndIsRepeatable(): void
    {
        if (TableHelper::columnExists($this->connection, 'order_delivery', 'position')) {
            $this->connection->executeStatement('ALTER TABLE `order_delivery` DROP COLUMN `position`');
        }

        $migration = new Migration1793979814AddPositionToOrderDelivery();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'order_delivery', 'position');
        static::assertTrue($column->isNotNull);
        static::assertSame('0', (string) $column->defaultValue);
    }
}
