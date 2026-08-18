<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1777952601BackfillPrimaryOrderTransaction;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1777952601BackfillPrimaryOrderTransaction::class)]
class Migration1777952601BackfillPrimaryOrderTransactionTest extends TestCase
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
        static::assertSame(1777952601, (new Migration1777952601BackfillPrimaryOrderTransaction())->getCreationTimestamp());
    }

    public function testMigrationBackfillsOrderWithNullPrimaryTransaction(): void
    {
        $orderId = $this->prepareOrderWithNullPrimaryTransaction();

        $this->migrate();
        $this->migrate();

        $row = $this->connection->fetchAssociative(
            'SELECT primary_order_transaction_id, primary_order_transaction_version_id FROM `order` WHERE id = :id',
            ['id' => $orderId]
        );

        static::assertIsArray($row);
        static::assertNotNull($row['primary_order_transaction_id']);
        static::assertNotNull($row['primary_order_transaction_version_id']);
    }

    public function testMigrationLeavesOrdersWithoutTransactionUntouched(): void
    {
        $orderId = $this->prepareOrder(false);

        $this->migrate();

        $row = $this->connection->fetchAssociative(
            'SELECT primary_order_transaction_id, primary_order_transaction_version_id FROM `order` WHERE id = :id',
            ['id' => $orderId]
        );

        static::assertIsArray($row);
        static::assertNull($row['primary_order_transaction_id']);
        static::assertNull($row['primary_order_transaction_version_id']);
    }

    public function testMigrationPicksLatestCreatedTransaction(): void
    {
        $orderId = $this->prepareOrder(false);
        $oldestId = $this->insertTransaction($orderId, '2020-01-01 00:00:00');
        $latestId = $this->insertTransaction($orderId, '2024-06-01 00:00:00');
        $midId = $this->insertTransaction($orderId, '2022-03-15 00:00:00');

        $this->migrate();

        $primaryId = $this->connection->fetchOne(
            'SELECT primary_order_transaction_id FROM `order` WHERE id = :id',
            ['id' => $orderId]
        );

        static::assertSame($latestId, $primaryId);
        static::assertNotSame($oldestId, $primaryId);
        static::assertNotSame($midId, $primaryId);
    }

    public function testMigrationDoesNotOverwriteAlreadySetPrimary(): void
    {
        $orderId = $this->prepareOrder(false);
        $transactionAId = $this->insertTransaction($orderId, '2024-06-01 00:00:00');
        $transactionBId = $this->insertTransaction($orderId, '2020-01-01 00:00:00');

        $this->connection->executeStatement(
            'UPDATE `order` SET primary_order_transaction_id = :transaction, primary_order_transaction_version_id = :version WHERE id = :id',
            [
                'transaction' => $transactionBId,
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'id' => $orderId,
            ]
        );

        $this->migrate();

        $primaryId = $this->connection->fetchOne(
            'SELECT primary_order_transaction_id FROM `order` WHERE id = :id',
            ['id' => $orderId]
        );

        static::assertSame($transactionBId, $primaryId);
        static::assertNotSame($transactionAId, $primaryId);
    }

    public function testMigrationProcessesMultipleOrders(): void
    {
        $orderIds = [];
        for ($i = 0; $i < 5; ++$i) {
            $orderId = $this->prepareOrder(true);
            $this->connection->executeStatement(
                'UPDATE `order` SET primary_order_transaction_id = NULL, primary_order_transaction_version_id = NULL WHERE id = :id',
                ['id' => $orderId]
            );
            $orderIds[] = $orderId;
        }

        $this->migrate();

        foreach ($orderIds as $orderId) {
            $row = $this->connection->fetchAssociative(
                'SELECT primary_order_transaction_id, primary_order_transaction_version_id FROM `order` WHERE id = :id',
                ['id' => $orderId]
            );
            static::assertIsArray($row);
            static::assertNotNull($row['primary_order_transaction_id']);
            static::assertNotNull($row['primary_order_transaction_version_id']);
        }
    }

    private function prepareOrderWithNullPrimaryTransaction(): string
    {
        $orderId = $this->prepareOrder(true);

        $this->connection->executeStatement(
            'UPDATE `order` SET primary_order_transaction_id = NULL, primary_order_transaction_version_id = NULL WHERE id = :id',
            ['id' => $orderId]
        );

        return $orderId;
    }

    private function prepareOrder(bool $withTransaction): string
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

        if ($withTransaction) {
            $this->insertTransaction($orderId, '2020-01-01');
        }

        return $orderId;
    }

    private function insertTransaction(string $orderId, string $createdAt): string
    {
        $transactionId = Uuid::fromHexToBytes(Uuid::randomHex());

        $paymentMethodId = $this->connection->executeQuery('SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`')->fetchOne();
        static::assertIsString($paymentMethodId);

        $this->connection->insert(
            '`order_transaction`',
            [
                'id' => $transactionId,
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'order_id' => $orderId,
                'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'state_id' => $this->fetchStateId(OrderTransactionStates::STATE_MACHINE),
                'payment_method_id' => $paymentMethodId,
                'amount' => 100,
                'created_at' => $createdAt,
            ]
        );

        return $transactionId;
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
        (new Migration1777952601BackfillPrimaryOrderTransaction())->update($this->connection);
    }
}
