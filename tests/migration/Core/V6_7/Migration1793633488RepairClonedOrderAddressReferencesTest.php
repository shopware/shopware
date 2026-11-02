<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1793633488RepairClonedOrderAddressReferences;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1793633488RepairClonedOrderAddressReferences::class)]
class Migration1793633488RepairClonedOrderAddressReferencesTest extends TestCase
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
        static::assertSame(1793633488, (new Migration1793633488RepairClonedOrderAddressReferences())->getCreationTimestamp());
    }

    public function testCopyIsPointedToItsOwnAddressWithTheSameContent(): void
    {
        $source = $this->insertOrder();
        $sourceAddress = $this->insertAddress($source, 'Main Street 1');
        $this->setBillingAddress($source, $sourceAddress);
        $sourceDelivery = $this->insertDelivery($source, $sourceAddress);

        $copy = $this->insertOrder();
        $copyAddress = $this->insertAddress($copy, 'Main Street 1');
        $this->setBillingAddress($copy, $sourceAddress);
        $copyDelivery = $this->insertDelivery($copy, $sourceAddress);

        $this->migrate();
        $this->migrate();

        static::assertSame($copyAddress, $this->fetchShippingAddress($copyDelivery));
        static::assertSame($copyAddress, $this->fetchBillingAddress($copy));
        static::assertSame($sourceAddress, $this->fetchShippingAddress($sourceDelivery));
        static::assertSame($sourceAddress, $this->fetchBillingAddress($source));
    }

    public function testCopyWithAnEditedAddressIsLeftAsItIs(): void
    {
        $source = $this->insertOrder();
        $sourceAddress = $this->insertAddress($source, 'Main Street 1');

        $copy = $this->insertOrder();
        $this->insertAddress($copy, 'Changed Street 2');
        $this->setBillingAddress($copy, $sourceAddress);
        $copyDelivery = $this->insertDelivery($copy, $sourceAddress);

        $this->migrate();

        static::assertSame($sourceAddress, $this->fetchShippingAddress($copyDelivery));
        static::assertSame($sourceAddress, $this->fetchBillingAddress($copy));
    }

    public function testOrderThatUsesItsOwnAddressIsLeftAsItIs(): void
    {
        $order = $this->insertOrder();
        $billingAddress = $this->insertAddress($order, 'Main Street 1');
        $shippingAddress = $this->insertAddress($order, 'Main Street 1');
        $this->setBillingAddress($order, $billingAddress);
        $delivery = $this->insertDelivery($order, $shippingAddress);

        $this->migrate();

        static::assertSame($shippingAddress, $this->fetchShippingAddress($delivery));
        static::assertSame($billingAddress, $this->fetchBillingAddress($order));
    }

    public function testCopyWhoseSourceAddressWasDeletedIsLeftAsItIs(): void
    {
        $deletedAddress = Uuid::randomBytes();

        $copy = $this->insertOrder();
        $this->insertAddress($copy, 'Main Street 1');
        $this->setBillingAddress($copy, $deletedAddress);

        $this->migrate();

        static::assertSame($deletedAddress, $this->fetchBillingAddress($copy));
    }

    private function migrate(): void
    {
        (new Migration1793633488RepairClonedOrderAddressReferences())->update($this->connection);
    }

    private function insertOrder(): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('`order`', [
            'id' => $id,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'currency_factor' => 1.0,
            'order_date_time' => '2020-01-01',
            'price' => json_encode(['netPrice' => 100, 'taxStatus' => 'gross', 'totalPrice' => 100, 'positionPrice' => 1], \JSON_THROW_ON_ERROR),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'state_id' => $this->fetchOpenStateId(OrderStates::STATE_MACHINE),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'billing_address_id' => Uuid::randomBytes(),
            'billing_address_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'shipping_costs' => '{}',
            'created_at' => '2020-01-01',
        ]);

        return $id;
    }

    private function insertAddress(string $orderId, string $street): string
    {
        $id = Uuid::randomBytes();

        $countryId = $this->connection->fetchOne('SELECT id FROM country LIMIT 1');
        static::assertIsString($countryId);

        $this->connection->insert('order_address', [
            'id' => $id,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'order_id' => $orderId,
            'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'country_id' => $countryId,
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'street' => $street,
            'zipcode' => '12345',
            'city' => 'Schöppingen',
            'created_at' => '2020-01-01',
        ]);

        return $id;
    }

    private function setBillingAddress(string $orderId, string $addressId): void
    {
        $this->connection->update('`order`', ['billing_address_id' => $addressId], ['id' => $orderId]);
    }

    private function insertDelivery(string $orderId, string $addressId): string
    {
        $id = Uuid::randomBytes();

        $shippingMethodId = $this->connection->fetchOne('SELECT id FROM shipping_method LIMIT 1');
        static::assertIsString($shippingMethodId);

        $this->connection->insert('order_delivery', [
            'id' => $id,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'order_id' => $orderId,
            'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'shipping_order_address_id' => $addressId,
            'shipping_order_address_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'state_id' => $this->fetchOpenStateId(OrderDeliveryStates::STATE_MACHINE),
            'shipping_method_id' => $shippingMethodId,
            'tracking_codes' => '[]',
            'shipping_date_earliest' => '2020-01-01',
            'shipping_date_latest' => '2020-01-02',
            'shipping_costs' => '{}',
            'created_at' => '2020-01-01',
        ]);

        return $id;
    }

    private function fetchShippingAddress(string $deliveryId): string
    {
        $addressId = $this->connection->fetchOne('SELECT shipping_order_address_id FROM order_delivery WHERE id = :id', ['id' => $deliveryId]);
        static::assertIsString($addressId);

        return $addressId;
    }

    private function fetchBillingAddress(string $orderId): string
    {
        $addressId = $this->connection->fetchOne('SELECT billing_address_id FROM `order` WHERE id = :id', ['id' => $orderId]);
        static::assertIsString($addressId);

        return $addressId;
    }

    private function fetchOpenStateId(string $stateMachine): string
    {
        $stateId = $this->connection->fetchOne(
            'SELECT state.id FROM state_machine_state state
            INNER JOIN state_machine machine ON machine.id = state.state_machine_id
            WHERE machine.technical_name = :machine AND state.technical_name = :state',
            ['machine' => $stateMachine, 'state' => 'open']
        );
        static::assertIsString($stateId);

        return $stateId;
    }
}
