<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1779944867TrimCustomerAddressFields;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1779944867TrimCustomerAddressFields::class)]
class Migration1779944867TrimCustomerAddressFieldsTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private string $customerId;

    private string $addressId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->customerId = Uuid::randomHex();
        $this->addressId = Uuid::randomHex();
    }

    protected function tearDown(): void
    {
        $this->connection->delete('customer', ['id' => Uuid::fromHexToBytes($this->customerId)]);
        $this->connection->delete('customer_address', ['id' => Uuid::fromHexToBytes($this->addressId)]);
    }

    public function testCreationTimestamp(): void
    {
        static::assertSame(1779944867, (new Migration1779944867TrimCustomerAddressFields())->getCreationTimestamp());
    }

    public function testMigrationTrimsCustomerAddressFieldsUsingPhpTrim(): void
    {
        $this->createCustomerAddress([
            'first_name' => "\tMax\n",
            'last_name' => "\0Mustermann\x0B",
            'street' => "\rMain Street 1 ",
            'zipcode' => "\n12345\t",
            'city' => ' Berlin ',
            'company' => ' Shopware ',
            'department' => "\tCore",
            'title' => " Dr.\n",
            'phone_number' => "\r123456\n",
            'additional_address_line1' => ' Line 1 ',
            'additional_address_line2' => "\x0BLine 2\0",
        ]);

        $migration = new Migration1779944867TrimCustomerAddressFields();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $address = $this->fetchCustomerAddress();

        static::assertSame('Max', $address['first_name']);
        static::assertSame('Mustermann', $address['last_name']);
        static::assertSame('Main Street 1', $address['street']);
        static::assertSame('12345', $address['zipcode']);
        static::assertSame('Berlin', $address['city']);
        static::assertSame('Shopware', $address['company']);
        static::assertSame('Core', $address['department']);
        static::assertSame('Dr.', $address['title']);
        static::assertSame('123456', $address['phone_number']);
        static::assertSame('Line 1', $address['additional_address_line1']);
        static::assertSame('Line 2', $address['additional_address_line2']);
    }

    /**
     * @param array<string, string|null> $addressFields
     */
    private function createCustomerAddress(array $addressFields): void
    {
        $customerId = Uuid::fromHexToBytes($this->customerId);
        $addressId = Uuid::fromHexToBytes($this->addressId);
        $createdAt = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $customer = [
            'id' => $customerId,
            'customer_group_id' => Uuid::fromHexToBytes(TestDefaults::FALLBACK_CUSTOMER_GROUP),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'default_billing_address_id' => $addressId,
            'default_shipping_address_id' => $addressId,
            'customer_number' => Uuid::randomHex(),
            'salutation_id' => $this->getValidSalutationId(),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'guest' => 0,
            'created_at' => $createdAt,
        ];

        if (TableHelper::columnExists($this->connection, 'customer', 'default_payment_method_id')) {
            $customer['default_payment_method_id'] = $this->getValidPaymentMethodId();
        }

        $this->connection->insert('customer', $customer);
        $this->connection->insert('customer_address', array_replace([
            'id' => $addressId,
            'customer_id' => $customerId,
            'country_id' => $this->getValidCountryId(),
            'salutation_id' => $this->getValidSalutationId(),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'street' => 'Main Street 1',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'created_at' => $createdAt,
        ], $addressFields));
    }

    private function getValidSalutationId(): string
    {
        $salutationId = $this->connection->fetchOne('SELECT `id` FROM `salutation` LIMIT 1');
        static::assertIsString($salutationId);

        return $salutationId;
    }

    private function getValidCountryId(): string
    {
        $countryId = $this->connection->fetchOne('SELECT `id` FROM `country` LIMIT 1');
        static::assertIsString($countryId);

        return $countryId;
    }

    private function getValidPaymentMethodId(): string
    {
        $paymentMethodId = $this->connection->fetchOne('SELECT `id` FROM `payment_method` LIMIT 1');
        static::assertIsString($paymentMethodId);

        return $paymentMethodId;
    }

    /**
     * @return array<string, string|null>
     */
    private function fetchCustomerAddress(): array
    {
        $address = $this->connection->fetchAssociative('
            SELECT
                `first_name`,
                `last_name`,
                `street`,
                `zipcode`,
                `city`,
                `company`,
                `department`,
                `title`,
                `phone_number`,
                `additional_address_line1`,
                `additional_address_line2`
            FROM `customer_address`
            WHERE `id` = :id
        ', ['id' => Uuid::fromHexToBytes($this->addressId)]);
        static::assertIsArray($address);

        /** @var array<string, string|null> $address */
        return $address;
    }
}
