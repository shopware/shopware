<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1794410950FixBusinessAccountTypeWithoutCompany;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1794410950FixBusinessAccountTypeWithoutCompany::class)]
class Migration1794410950FixBusinessAccountTypeWithoutCompanyTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794410950, (new Migration1794410950FixBusinessAccountTypeWithoutCompany())->getCreationTimestamp());
    }

    public function testResetsBusinessCustomersWithoutCompanyToPrivate(): void
    {
        $withNullCompany = $this->insertCustomer(accountType: CustomerEntity::ACCOUNT_TYPE_BUSINESS, company: null, vatIds: null);
        $withEmptyCompany = $this->insertCustomer(accountType: CustomerEntity::ACCOUNT_TYPE_BUSINESS, company: '', vatIds: null);
        $withWhitespaceCompany = $this->insertCustomer(accountType: CustomerEntity::ACCOUNT_TYPE_BUSINESS, company: '   ', vatIds: null);
        $withVatIds = $this->insertCustomer(accountType: CustomerEntity::ACCOUNT_TYPE_BUSINESS, company: null, vatIds: '["DE123456789"]');
        $withEmptyVatIds = $this->insertCustomer(accountType: CustomerEntity::ACCOUNT_TYPE_BUSINESS, company: null, vatIds: '[]');
        $withWhitespaceCompanyAndVatIds = $this->insertCustomer(accountType: CustomerEntity::ACCOUNT_TYPE_BUSINESS, company: '   ', vatIds: '["DE123456789"]');

        $migration = new Migration1794410950FixBusinessAccountTypeWithoutCompany();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $this->fetchAccountType($withNullCompany));
        static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $this->fetchAccountType($withEmptyCompany));
        static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $this->fetchAccountType($withWhitespaceCompany));
        static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $this->fetchAccountType($withVatIds));
        static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $this->fetchAccountType($withEmptyVatIds));
        static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $this->fetchAccountType($withWhitespaceCompanyAndVatIds));
    }

    public function testKeepsBusinessCustomerWithCompany(): void
    {
        $customerId = $this->insertCustomer(accountType: CustomerEntity::ACCOUNT_TYPE_BUSINESS, company: 'shopware AG', vatIds: null);

        (new Migration1794410950FixBusinessAccountTypeWithoutCompany())->update($this->connection);

        static::assertSame(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $this->fetchAccountType($customerId));
    }

    public function testKeepsPrivateCustomersUntouched(): void
    {
        $customerId = $this->insertCustomer(accountType: CustomerEntity::ACCOUNT_TYPE_PRIVATE, company: null, vatIds: null);

        (new Migration1794410950FixBusinessAccountTypeWithoutCompany())->update($this->connection);

        static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $this->fetchAccountType($customerId));
    }

    public function testResetsMoreCustomersThanOneChunk(): void
    {
        for ($i = 0; $i < 1500; ++$i) {
            $this->insertCustomer(accountType: CustomerEntity::ACCOUNT_TYPE_BUSINESS, company: null, vatIds: null);
        }

        (new Migration1794410950FixBusinessAccountTypeWithoutCompany())->update($this->connection);

        $remainingBusinessCustomers = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `customer` WHERE `account_type` = :business',
            ['business' => CustomerEntity::ACCOUNT_TYPE_BUSINESS]
        );

        static::assertSame(0, $remainingBusinessCustomers);
    }

    private function insertCustomer(string $accountType, ?string $company, ?string $vatIds): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('customer', [
            'id' => $id,
            'customer_group_id' => Uuid::fromHexToBytes(TestDefaults::FALLBACK_CUSTOMER_GROUP),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'default_billing_address_id' => Uuid::randomBytes(),
            'default_shipping_address_id' => Uuid::randomBytes(),
            'customer_number' => '1337',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => Uuid::randomHex() . '@example.com',
            'account_type' => $accountType,
            'company' => $company,
            'vat_ids' => $vatIds,
            'guest' => 0,
            'active' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    private function fetchAccountType(string $customerId): string
    {
        $accountType = $this->connection->fetchOne(
            'SELECT `account_type` FROM `customer` WHERE `id` = :id',
            ['id' => $customerId]
        );

        static::assertIsString($accountType);

        return $accountType;
    }
}
