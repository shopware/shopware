<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1676272000AddAccountTypeToCustomer;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 *
 * @phpstan-type CustomerData array{account_type:string, id: string, billing_company: ?string, company: ?string, vat_ids: ?string}
 */
#[CoversClass(Migration1676272000AddAccountTypeToCustomer::class)]
class Migration1676272000AddAccountTypeToCustomerTest extends TestCase
{
    private Connection $connection;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new TestDataCollection();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM `customer_address`');
        $this->connection->executeStatement('DELETE FROM `customer`');
    }

    public function testTimestampIsCorrect(): void
    {
        $migration = new Migration1676272000AddAccountTypeToCustomer();
        static::assertEquals('1676272000', $migration->getCreationTimestamp());
    }

    public function testAddAccountType(): void
    {
        $this->dropAccountType();

        $migration = new Migration1676272000AddAccountTypeToCustomer();
        $migration->update($this->connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'customer', 'account_type'));
    }

    public function testAddAccountTypeMultiple(): void
    {
        $this->dropAccountType();

        $migration = new Migration1676272000AddAccountTypeToCustomer();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'customer', 'account_type'));
    }

    public function testSetCustomerAccountTypeToPrivateIfCompanyAndVatIdsIsBothNull(): void
    {
        $this->createCustomer();

        $migration = new Migration1676272000AddAccountTypeToCustomer();
        $migration->update($this->connection);

        $customer = $this->fetchCustomer();

        static::assertNotFalse($customer);
        static::assertEquals(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $customer['account_type']);
        static::assertNull($customer['company']);
        static::assertNull($customer['vat_ids']);
    }

    public function testSetCustomerAccountTypeToBusinessIfCompanyIsNotNull(): void
    {
        $this->createCustomer(['company' => 'Shopware AG'], ['company' => 'Shopware AG']);

        $customer = $this->fetchCustomer();

        static::assertIsArray($customer);
        static::assertEquals(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $customer['account_type']);

        $migration = new Migration1676272000AddAccountTypeToCustomer();
        $migration->update($this->connection);

        $customer = $this->fetchCustomer();

        static::assertIsArray($customer);
        static::assertEquals(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $customer['account_type']);
    }

    public function testSetCustomerAccountTypeToBusinessIfVatIdsIsNotNull(): void
    {
        $this->createCustomer(['vat_ids' => json_encode(['DE123456789'], \JSON_THROW_ON_ERROR)]);

        $customer = $this->fetchCustomer();

        static::assertIsArray($customer);
        static::assertEquals(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $customer['account_type']);

        $migration = new Migration1676272000AddAccountTypeToCustomer();
        $migration->update($this->connection);

        $customer = $this->fetchCustomer();

        static::assertIsArray($customer);
        static::assertEquals(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $customer['account_type']);
        static::assertNotNull($customer['vat_ids']);
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \in_array($columnName, array_column($this->connection->fetchAllAssociative(\sprintf('SHOW COLUMNS FROM `%s`', $table)), 'Field'), true);
    }

    /**
     * @param array<string, string|bool|null> $customerOverrides
     * @param array<string, string|bool|null> $addressOverrides
     */
    private function createCustomer(array $customerOverrides = [], array $addressOverrides = []): void
    {
        $billingAddressId = $this->ids->create('billingAddress');
        $defaultCountry = $this->connection->fetchOne('SELECT id FROM country WHERE active = 1 ORDER BY `position`');
        $defaultPaymentMethod = $this->connection->fetchOne('SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`');
        $now = new \DateTimeImmutable();

        $customerAddress = array_merge([
            'id' => Uuid::fromHexToBytes($billingAddressId),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schoöppingen',
            'zipcode' => '12345',
            'country_id' => $defaultCountry,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'customer_id' => Uuid::fromHexToBytes($this->ids->get('customer')),
        ], $addressOverrides);

        $customer = array_merge([
            'id' => Uuid::fromHexToBytes($this->ids->create('customer')),
            'customer_number' => '1337',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => 'shopware',
            'created_at' => $now->format('Y-m-d H:i:s'),
            'default_payment_method_id' => $defaultPaymentMethod,
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'customer_group_id' => Uuid::fromHexToBytes(TestDefaults::FALLBACK_CUSTOMER_GROUP),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'default_billing_address_id' => Uuid::fromHexToBytes($billingAddressId),
            'default_shipping_address_id' => Uuid::randomBytes(),
        ], $customerOverrides);

        $this->connection->insert('customer', $customer);
        $this->connection->insert('customer_address', $customerAddress);
    }

    private function dropAccountType(): void
    {
        if ($this->hasColumn('customer', 'account_type')) {
            $this->connection->executeStatement('ALTER TABLE `customer` DROP COLUMN `account_type`');
        }
    }

    /**
     * @return false|CustomerData
     */
    private function fetchCustomer(): false|array
    {
        /** @var false|CustomerData $results */
        $results = $this->connection->fetchAssociative(
            'SELECT
                        LOWER(HEX(`customer`.`id`)) AS `id`,
                        `customer`.`company`,
                        `customer`.`account_type`,
                        `customer`.`vat_ids`,
                        `customer_address`.`company` as `billing_company`
                    FROM
                        `customer`
                    JOIN
                        `customer_address` ON `customer`.`default_billing_address_id` = `customer_address`.`id`
                    WHERE `customer`.`id` = :customerId',
            ['customerId' => Uuid::fromHexToBytes($this->ids->get('customer'))]
        );

        return $results;
    }
}
