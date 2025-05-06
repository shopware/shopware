<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1728040170AddPrimaryOrderTransaction;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1728040170AddPrimaryOrderTransaction::class)]
class Migration1728040170AddPrimaryOrderTransactionTest extends TestCase
{
    use AddColumnTrait;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    protected function tearDown(): void
    {
        $this->connection->delete('`order`');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1728040170, (new Migration1728040170AddPrimaryOrderTransaction())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        $this->prepareOldDatabaseEntry();

        $this->migrate();
        $this->migrate();

        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableColumns(OrderDefinition::ENTITY_NAME);

        static::assertArrayHasKey('primary_order_transaction_id', $columns);
        static::assertArrayHasKey('primary_order_transaction_version_id', $columns);

        $query = $this->connection->createQueryBuilder();
        $query->select('*');
        $query->from('`order`');
        $result = $query->executeQuery()->fetchAllAssociative();

        foreach ($result as $row) {
            static::assertNotNull($row['primary_order_transaction_id']);
            static::assertNotNull($row['primary_order_transaction_version_id']);
        }
    }

    private function prepareOldDatabaseEntry(): void
    {
        $orderId = Uuid::fromHexToBytes(Uuid::randomHex());
        $defaultPaymentMethodId = $this->connection->executeQuery('SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`')->fetchOne();

        $machineId = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT id FROM state_machine WHERE technical_name = :state', ['state' => 'order_transaction.state']);

        $stateId = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :state AND state_machine_id = :machineId', ['state' => 'open', 'machineId' => $machineId]);

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
                ]),
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
                'state_id' => $stateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                'billing_address_id' => Uuid::randomBytes(),
                'billing_address_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'shipping_costs' => '{}',
                'created_at' => '2020-01-01',
            ]
        );

        $this->connection->insert(
            '`order_transaction`',
            [
                'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'order_id' => $orderId,
                'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'state_id' => $stateId,
                'payment_method_id' => $defaultPaymentMethodId,
                'amount' => 100,
                'created_at' => '2020-01-01',
            ]
        );
    }

    private function migrate(): void
    {
        (new Migration1728040170AddPrimaryOrderTransaction())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->dropIndexIfExists($this->connection, 'order', 'uidx.order.primary_order_transaction');

        if ($this->columnExists($this->connection, 'order', 'primary_order_transaction_id')) {
            $this->connection->executeStatement('ALTER TABLE `order` DROP COLUMN `primary_order_transaction_id`');
        }

        if ($this->columnExists($this->connection, 'order', 'primary_order_transaction_version_id')) {
            $this->connection->executeStatement('ALTER TABLE `order` DROP COLUMN `primary_order_transaction_version_id`');
        }
    }

    private function dropIndexIfExists(Connection $connection, string $table, string $indexName): void
    {
        $sql = \sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $indexName);

        try {
            $connection->executeStatement($sql);
        } catch (\Throwable $e) {
        }
    }
}
