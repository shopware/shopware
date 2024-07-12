<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1720094362AddStateForeignKeyToOrderDelivery;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(Migration1720094362AddStateForeignKeyToOrderDelivery::class)]
#[Package('checkout')]
class Migration1720094362AddStateForeignKeyToOrderDeliveryTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrate(): void
    {
        try {
            $this->rollback();

            $initialState = $this->getContainer()->get(InitialStateIdLoader::class)->get('order_delivery.state');
            $otherState = $this->getContainer()->get(StateMachineRegistry::class)->getStateMachine(OrderDeliveryStates::STATE_MACHINE, Context::createDefaultContext())->getStates()?->filter(function (StateMachineStateEntity $state) use ($initialState) {
                return $state->getId() !== $initialState;
            })->first()?->getId() ?? Uuid::randomHex();
            $invalidState = Uuid::randomHex();

            $this->createOrderDelivery($initialState);
            $this->createOrderDelivery($otherState);
            $this->createOrderDelivery($invalidState);
            static::assertSame([
                strtoupper($initialState) => '1',
                strtoupper($otherState) => '1',
                strtoupper($invalidState) => '1',
            ], $this->getStateCount());

            $this->migrate();
            $this->migrate();

            static::assertTrue($this->hasForeignKey());

            static::assertSame([
                strtoupper($initialState) => '2',
                strtoupper($otherState) => '1',
            ], $this->getStateCount());
        } finally {
            $this->connection->executeStatement('DELETE FROM `order` WHERE 1');
        }
    }

    private function migrate(): void
    {
        (new Migration1720094362AddStateForeignKeyToOrderDelivery())->update($this->connection);
    }

    private function rollback(): void
    {
        if ($this->hasForeignKey()) {
            $this->connection->executeStatement('ALTER TABLE `order_delivery` DROP FOREIGN KEY `fk.order_delivery.state_id`');
        }
    }

    private function createOrderDelivery(string $orderDeliveryState): string
    {
        $this->connection->executeStatement(<<<SQL
            INSERT INTO `order` SET
                id = :orderId,
                version_id = :defaultVersion,
                state_id = (SELECT `initial_state_id` FROM `state_machine` WHERE `technical_name` = 'order.state'),
                order_number = '100000001',
                currency_id = :defaultCurrency,
                language_id = :defaultLanguage,
                sales_channel_id = :defaultSalesChannel,
                billing_address_id = :billingAddressId,
                billing_address_version_id = :defaultVersion,
                price = '{}',
                order_date_time = NOW(),
                shipping_costs = '{}',
                created_at = NOW();
    SQL, [
            'orderId' => $orderId = Uuid::randomBytes(),
            'defaultVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'defaultCurrency' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'defaultLanguage' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'defaultSalesChannel' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'billingAddressId' => Uuid::randomBytes(),
        ]);

        $this->connection->executeStatement(<<<SQL
            INSERT INTO `order_delivery` SET
                id = :orderDeliveryId,
                version_id = :defaultVersion,
                order_id = :orderId,
                order_version_id = :defaultVersion,
                shipping_method_id = (SELECT `id` FROM `shipping_method` LIMIT 1),
                state_id = :orderDeliveryState,
                tracking_codes = '[]',
                shipping_date_earliest = NOW(),
                shipping_date_latest = NOW(),
                shipping_costs = '{}',
                created_at = NOW();
    SQL, [
            'orderDeliveryId' => $orderDeliveryId = Uuid::randomBytes(),
            'defaultVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'orderId' => $orderId,
            'shippingMethodId' => Uuid::randomBytes(),
            'orderDeliveryState' => Uuid::fromHexToBytes($orderDeliveryState),
        ]);

        return $orderDeliveryId;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getStateCount(): array
    {
        return $this->connection->fetchAllKeyValue(<<<SQL
            SELECT HEX(state_id), COUNT(*) as count FROM `order_delivery` GROUP BY state_id;
        SQL);
    }

    private function hasForeignKey(): bool
    {
        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableForeignKeys('order_delivery');

        return (bool) \array_filter($columns, static fn (ForeignKeyConstraint $column) => $column->getForeignTableName() === 'state_machine_state' && $column->getLocalColumns() === ['state_id'] && $column->getForeignColumns() === ['id']);
    }
}
