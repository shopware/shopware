<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1697792159FixOrderDeliveryAddressConstraint;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1697792159FixOrderDeliveryAddressConstraint
 */
#[Package('checkout')]
class Migration1697792159FixOrderDeliveryAddressConstraintTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement('
                ALTER TABLE `order_delivery` DROP FOREIGN KEY `fk.order_delivery.shipping_order_address_id`
            ');
        } catch (\Throwable) {
        }
    }

    public function testUpdate(): void
    {
        $migration = new Migration1697792159FixOrderDeliveryAddressConstraint();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->keyExists('fk.order_delivery.shipping_order_address_id'));
    }

    private function keyExists(string $keyName): bool
    {
        return $this->connection->executeQuery(
            'SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "order_delivery"
            AND CONSTRAINT_NAME = :keyName;',
            ['keyName' => $keyName],
        )->fetchOne() !== false;
    }
}
