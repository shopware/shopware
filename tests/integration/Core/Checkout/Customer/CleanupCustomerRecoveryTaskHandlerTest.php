<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CleanupCustomerRecoveryTaskHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class CleanupCustomerRecoveryTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CleanupCustomerRecoveryTaskHandler $handler;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = static::getContainer()->get(CleanupCustomerRecoveryTaskHandler::class);
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testExpiredRecoveryIsDeleted(): void
    {
        $customerId = $this->createCustomer();

        $expiredAt = new \DateTime();
        $expiredAt->modify('-3 hour');
        $this->createCustomerRecovery($customerId, $expiredAt);

        $this->handler->run();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM customer_recovery WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );

        static::assertSame('0', (string) $count);
    }

    public function testNonExpiredRecoveryIsKept(): void
    {
        $customerId = $this->createCustomer();

        $recentAt = new \DateTime();
        $recentAt->modify('-1 hour');
        $this->createCustomerRecovery($customerId, $recentAt);

        $this->handler->run();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM customer_recovery WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );

        static::assertSame('1', (string) $count);
    }

    public function testMixedRecordsOnlyDeletesExpired(): void
    {
        $expiredCustomerId = $this->createCustomer();
        $recentCustomerId = $this->createCustomer();

        $expiredAt = new \DateTime();
        $expiredAt->modify('-3 hour');
        $this->createCustomerRecovery($expiredCustomerId, $expiredAt);

        $recentAt = new \DateTime();
        $recentAt->modify('-30 minutes');
        $this->createCustomerRecovery($recentCustomerId, $recentAt);

        $this->handler->run();

        $expiredCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM customer_recovery WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($expiredCustomerId)]
        );
        static::assertSame('0', (string) $expiredCount);

        $recentCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM customer_recovery WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($recentCustomerId)]
        );
        static::assertSame('1', (string) $recentCount);
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $salutationId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM salutation LIMIT 1');
        $paymentMethodId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM payment_method LIMIT 1');
        $groupId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM customer_group LIMIT 1');
        $salesChannelId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM sales_channel LIMIT 1');
        $countryId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM country LIMIT 1');

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('customer', [
            'id' => Uuid::fromHexToBytes($customerId),
            'customer_number' => 'TEST-' . $customerId,
            'salutation_id' => Uuid::fromHexToBytes($salutationId),
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => $customerId . '@example.com',
            'password' => password_hash('test1234', \PASSWORD_BCRYPT),
            'default_billing_address_id' => Uuid::fromHexToBytes($addressId),
            'default_shipping_address_id' => Uuid::fromHexToBytes($addressId),
            'default_payment_method_id' => Uuid::fromHexToBytes($paymentMethodId),
            'customer_group_id' => Uuid::fromHexToBytes($groupId),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'created_at' => $now,
        ]);

        $this->connection->insert('customer_address', [
            'id' => Uuid::fromHexToBytes($addressId),
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'salutation_id' => Uuid::fromHexToBytes($salutationId),
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'street' => 'Test Street 1',
            'zipcode' => '12345',
            'city' => 'Test City',
            'country_id' => Uuid::fromHexToBytes($countryId),
            'created_at' => $now,
        ]);

        return $customerId;
    }

    private function createCustomerRecovery(string $customerId, \DateTime $createdAt): void
    {
        $this->connection->insert('customer_recovery', [
            'id' => Uuid::randomBytes(),
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'hash' => Uuid::randomHex(),
            'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
