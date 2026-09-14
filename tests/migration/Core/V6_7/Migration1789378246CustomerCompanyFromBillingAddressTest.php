<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1789378246CustomerCompanyFromBillingAddress;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1789378246CustomerCompanyFromBillingAddress::class)]
class Migration1789378246CustomerCompanyFromBillingAddressTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1789378246, (new Migration1789378246CustomerCompanyFromBillingAddress())->getCreationTimestamp());
    }

    #[DataProvider('customerProvider')]
    public function testMigration(string $accountType, ?string $company, ?string $addressCompany, ?string $expected): void
    {
        $customerId = $this->createCustomer($accountType, $company, $addressCompany);

        $migration = new Migration1789378246CustomerCompanyFromBillingAddress();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame($expected, $this->company($customerId));
    }

    /**
     * @return iterable<string, array{string, string|null, string|null, string|null}>
     */
    public static function customerProvider(): iterable
    {
        yield 'a commercial account without a company takes the one of its billing address' => ['business', null, 'Acme GmbH', 'Acme GmbH'];
        yield 'a blank company counts as none' => ['business', '   ', 'Acme GmbH', 'Acme GmbH'];
        yield 'an existing company is kept' => ['business', 'Existing AG', 'Acme GmbH', 'Existing AG'];
        yield 'a private account is left alone' => ['private', null, 'Acme GmbH', null];
        yield 'a blank address company is ignored' => ['business', null, '   ', null];
        yield 'an address without a company changes nothing' => ['business', null, null, null];
    }

    private function createCustomer(string $accountType, ?string $company, ?string $addressCompany): string
    {
        $customerId = Uuid::randomBytes();
        $addressId = Uuid::randomBytes();
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('customer', [
            'id' => $customerId,
            'customer_group_id' => Uuid::fromHexToBytes(TestDefaults::FALLBACK_CUSTOMER_GROUP),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'default_billing_address_id' => $addressId,
            'default_shipping_address_id' => $addressId,
            'customer_number' => Uuid::randomHex(),
            'first_name' => '',
            'last_name' => '',
            'email' => Uuid::randomHex() . '@example.com',
            'account_type' => $accountType,
            'company' => $company,
            'active' => 1,
            'guest' => 0,
            'created_at' => $now,
        ]);

        $this->connection->insert('customer_address', [
            'id' => $addressId,
            'customer_id' => $customerId,
            'country_id' => $this->connection->fetchOne('SELECT `id` FROM `country` LIMIT 1'),
            'first_name' => '',
            'last_name' => '',
            'street' => 'Ebbinghoff 10',
            'city' => 'Schöppingen',
            'company' => $addressCompany,
            'created_at' => $now,
        ]);

        return $customerId;
    }

    private function company(string $customerId): ?string
    {
        $company = $this->connection->fetchOne('SELECT `company` FROM `customer` WHERE `id` = :id', ['id' => $customerId]);

        return $company === false ? null : $company;
    }
}
