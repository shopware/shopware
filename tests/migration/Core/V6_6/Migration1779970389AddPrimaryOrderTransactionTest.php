<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1779970389AddPrimaryOrderTransaction;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1779970389AddPrimaryOrderTransaction::class)]
class Migration1779970389AddPrimaryOrderTransactionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testMigrationAddsAndBackfillsPrimaryOrderTransaction(): void
    {
        $this->rollback();

        $orderId = Uuid::randomHex();
        $olderTransactionId = Uuid::randomHex();
        $newerTransactionId = Uuid::randomHex();

        try {
            $this->createOrder($orderId);
            $this->createOrderTransaction($orderId, $olderTransactionId, '2024-01-01 00:00:00.000');
            $this->createOrderTransaction($orderId, $newerTransactionId, '2024-01-02 00:00:00.000');

            $migration = new Migration1779970389AddPrimaryOrderTransaction();
            $migration->update($this->connection);
            $migration->update($this->connection);

            static::assertTrue($this->columnExists('primary_order_transaction_id'));
            static::assertTrue($this->columnExists('primary_order_transaction_version_id'));
            static::assertTrue($this->indexExists('uidx.order.primary_order_transaction'));

            $primaryTransaction = $this->connection->fetchAssociative(
                'SELECT `primary_order_transaction_id`, `primary_order_transaction_version_id`
                 FROM `order`
                 WHERE `id` = :orderId',
                ['orderId' => Uuid::fromHexToBytes($orderId)]
            );

            static::assertIsArray($primaryTransaction);
            static::assertIsString($primaryTransaction['primary_order_transaction_id']);
            static::assertIsString($primaryTransaction['primary_order_transaction_version_id']);
            static::assertSame($newerTransactionId, Uuid::fromBytesToHex($primaryTransaction['primary_order_transaction_id']));
            static::assertSame(Defaults::LIVE_VERSION, Uuid::fromBytesToHex($primaryTransaction['primary_order_transaction_version_id']));
        } finally {
            $this->connection->delete('order_transaction', ['order_id' => Uuid::fromHexToBytes($orderId)]);
            $this->connection->executeStatement(
                'DELETE FROM `order` WHERE `id` = :orderId',
                ['orderId' => Uuid::fromHexToBytes($orderId)]
            );
        }
    }

    private function rollback(): void
    {
        if ($this->indexExists('uidx.order.primary_order_transaction')) {
            $this->connection->executeStatement('ALTER TABLE `order` DROP INDEX `uidx.order.primary_order_transaction`');
        }

        if ($this->columnExists('primary_order_transaction_id')) {
            $this->connection->executeStatement('ALTER TABLE `order` DROP COLUMN `primary_order_transaction_id`');
        }

        if ($this->columnExists('primary_order_transaction_version_id')) {
            $this->connection->executeStatement('ALTER TABLE `order` DROP COLUMN `primary_order_transaction_version_id`');
        }
    }

    private function createOrder(string $orderId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `order`
                (`id`, `version_id`, `state_id`, `order_number`, `currency_id`, `language_id`, `sales_channel_id`, `billing_address_id`, `billing_address_version_id`, `price`, `order_date_time`, `shipping_costs`, `created_at`)
             VALUES
                (:id, :versionId, :stateId, :orderNumber, :currencyId, :languageId, :salesChannelId, :billingAddressId, :billingAddressVersionId, :price, :orderDateTime, :shippingCosts, :createdAt)',
            [
                'id' => Uuid::fromHexToBytes($orderId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'stateId' => Uuid::fromHexToBytes($this->getInitialStateId(OrderStates::STATE_MACHINE)),
                'orderNumber' => Uuid::randomHex(),
                'currencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY),
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'salesChannelId' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                'billingAddressId' => Uuid::randomBytes(),
                'billingAddressVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'price' => '{}',
                'orderDateTime' => '2024-01-01 00:00:00.000',
                'shippingCosts' => '{}',
                'createdAt' => '2024-01-01 00:00:00.000',
            ]
        );
    }

    private function createOrderTransaction(string $orderId, string $transactionId, string $createdAt): void
    {
        $this->connection->insert('order_transaction', [
            'id' => Uuid::fromHexToBytes($transactionId),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'order_id' => Uuid::fromHexToBytes($orderId),
            'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'payment_method_id' => $this->getValidPaymentMethodId(),
            'amount' => '{}',
            'state_id' => Uuid::fromHexToBytes($this->getInitialStateId(OrderTransactionStates::STATE_MACHINE)),
            'created_at' => $createdAt,
        ]);
    }

    private function getInitialStateId(string $stateMachine): string
    {
        return static::getContainer()->get(InitialStateIdLoader::class)->get($stateMachine);
    }

    private function getValidPaymentMethodId(): string
    {
        $paymentMethodId = $this->connection->fetchOne('SELECT `id` FROM `payment_method` LIMIT 1');
        static::assertIsString($paymentMethodId);

        return $paymentMethodId;
    }

    private function columnExists(string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SHOW COLUMNS FROM `order` WHERE `Field` LIKE :column',
            ['column' => $column]
        );
    }

    private function indexExists(string $index): bool
    {
        return (bool) $this->connection->fetchOne(
            'SHOW INDEXES FROM `order` WHERE `key_name` LIKE :index',
            ['index' => $index]
        );
    }
}
