<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1777952600BackfillPrimaryOrderDelivery;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1777952600BackfillPrimaryOrderDelivery::class)]
class Migration1777952600BackfillPrimaryOrderDeliveryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        $this->connection->delete('`order`');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1777952600, (new Migration1777952600BackfillPrimaryOrderDelivery())->getCreationTimestamp());
    }

    public function testMigrationBackfillsOrderWithNullPrimaryDelivery(): void
    {
        $orderId = $this->prepareOrderWithNullPrimaryDelivery();

        $this->migrate();
        $this->migrate();

        $row = $this->connection->fetchAssociative(
            'SELECT primary_order_delivery_id, primary_order_delivery_version_id FROM `order` WHERE id = :id',
            ['id' => $orderId]
        );

        static::assertIsArray($row);
        static::assertNotNull($row['primary_order_delivery_id']);
        static::assertNotNull($row['primary_order_delivery_version_id']);
    }

    public function testMigrationLeavesOrdersWithoutDeliveryUntouched(): void
    {
        $orderId = $this->prepareOrder(false);

        $this->migrate();

        $row = $this->connection->fetchAssociative(
            'SELECT primary_order_delivery_id, primary_order_delivery_version_id FROM `order` WHERE id = :id',
            ['id' => $orderId]
        );

        static::assertIsArray($row);
        static::assertNull($row['primary_order_delivery_id']);
        static::assertNull($row['primary_order_delivery_version_id']);
    }

    public function testMigrationPicksDeliveryWithHighestShippingCost(): void
    {
        $orderId = $this->prepareOrder(false);
        $cheapDeliveryId = $this->insertDelivery($orderId, 5.0);
        $expensiveDeliveryId = $this->insertDelivery($orderId, 25.0);
        $midDeliveryId = $this->insertDelivery($orderId, 15.0);

        $this->migrate();

        $primaryId = $this->connection->fetchOne(
            'SELECT primary_order_delivery_id FROM `order` WHERE id = :id',
            ['id' => $orderId]
        );

        static::assertSame($expensiveDeliveryId, $primaryId);
        static::assertNotSame($cheapDeliveryId, $primaryId);
        static::assertNotSame($midDeliveryId, $primaryId);
    }

    public function testMigrationDoesNotOverwriteAlreadySetPrimary(): void
    {
        $orderId = $this->prepareOrder(false);
        $deliveryAId = $this->insertDelivery($orderId, 50.0);
        $deliveryBId = $this->insertDelivery($orderId, 10.0);

        $this->connection->executeStatement(
            'UPDATE `order` SET primary_order_delivery_id = :delivery, primary_order_delivery_version_id = :version WHERE id = :id',
            [
                'delivery' => $deliveryBId,
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'id' => $orderId,
            ]
        );

        $this->migrate();

        $primaryId = $this->connection->fetchOne(
            'SELECT primary_order_delivery_id FROM `order` WHERE id = :id',
            ['id' => $orderId]
        );

        static::assertSame($deliveryBId, $primaryId);
        static::assertNotSame($deliveryAId, $primaryId);
    }

    public function testMigrationProcessesMultipleOrders(): void
    {
        $orderIds = [];
        for ($i = 0; $i < 5; ++$i) {
            $orderId = $this->prepareOrder(true);
            $this->connection->executeStatement(
                'UPDATE `order` SET primary_order_delivery_id = NULL, primary_order_delivery_version_id = NULL WHERE id = :id',
                ['id' => $orderId]
            );
            $orderIds[] = $orderId;
        }

        $this->migrate();

        foreach ($orderIds as $orderId) {
            $row = $this->connection->fetchAssociative(
                'SELECT primary_order_delivery_id, primary_order_delivery_version_id FROM `order` WHERE id = :id',
                ['id' => $orderId]
            );
            static::assertIsArray($row);
            static::assertNotNull($row['primary_order_delivery_id']);
            static::assertNotNull($row['primary_order_delivery_version_id']);
        }
    }

    private function prepareOrderWithNullPrimaryDelivery(): string
    {
        $orderId = $this->prepareOrder(true);

        $this->connection->executeStatement(
            'UPDATE `order` SET primary_order_delivery_id = NULL, primary_order_delivery_version_id = NULL WHERE id = :id',
            ['id' => $orderId]
        );

        return $orderId;
    }

    private function prepareOrder(bool $withDelivery): string
    {
        $orderId = Uuid::fromHexToBytes(Uuid::randomHex());

        $this->connection->insert(
            '`order`',
            [
                'id' => $orderId,
                'currency_factor' => 1.0,
                'order_date_time' => '2020-01-01',
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'price' => json_encode([
                    'netPrice' => 100,
                    'taxStatus' => 'gross',
                    'totalPrice' => 100,
                    'positionPrice' => 1,
                ], \JSON_THROW_ON_ERROR),
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
                'state_id' => $this->fetchStateId(OrderStates::STATE_MACHINE),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                'billing_address_id' => Uuid::randomBytes(),
                'billing_address_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'shipping_costs' => '{}',
                'created_at' => '2020-01-01',
            ]
        );

        if ($withDelivery) {
            $this->insertDelivery($orderId, 0.0);
        }

        return $orderId;
    }

    private function insertDelivery(string $orderId, float $unitPrice): string
    {
        $deliveryId = Uuid::fromHexToBytes(Uuid::randomHex());

        $shippingMethodId = $this->connection->executeQuery('SELECT id FROM shipping_method WHERE active = 1')->fetchOne();
        static::assertIsString($shippingMethodId);

        $this->connection->insert(
            '`order_delivery`',
            [
                'id' => $deliveryId,
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'order_id' => $orderId,
                'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'state_id' => $this->fetchStateId(OrderDeliveryStates::STATE_MACHINE),
                'shipping_method_id' => $shippingMethodId,
                'tracking_codes' => '["code"]',
                'shipping_date_earliest' => '2020-01-01',
                'shipping_date_latest' => '2025-01-01',
                'shipping_costs' => json_encode(['unitPrice' => $unitPrice], \JSON_THROW_ON_ERROR),
                'created_at' => '2020-01-01',
            ]
        );

        return $deliveryId;
    }

    private function fetchStateId(string $stateMachine): string
    {
        $machineId = $this->connection
            ->fetchOne('SELECT id FROM state_machine WHERE technical_name = :state', ['state' => $stateMachine]);
        static::assertIsString($machineId);

        $stateId = $this->connection
            ->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :state AND state_machine_id = :machineId', ['state' => 'open', 'machineId' => $machineId]);
        static::assertIsString($stateId);

        return $stateId;
    }

    private function migrate(): void
    {
        (new Migration1777952600BackfillPrimaryOrderDelivery())->update($this->connection);
    }
}
