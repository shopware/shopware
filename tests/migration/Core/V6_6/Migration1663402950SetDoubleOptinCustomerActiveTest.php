<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1663402950SetDoubleOptinCustomerActive;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1663402950SetDoubleOptinCustomerActive::class)]
class Migration1663402950SetDoubleOptinCustomerActiveTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $customerId = Uuid::randomBytes();
        $countAffectedRows = $this->addCustomerWithDoubleOptInButNotConfirmed($customerId);
        static::assertEquals(1, $countAffectedRows);
        static::assertFalse($this->checkCustomerIsActive($customerId));

        $migration = new Migration1663402950SetDoubleOptinCustomerActive();
        $migration->update($this->connection);
        static::assertTrue($this->checkCustomerIsActive($customerId));
    }

    public function testCanBeExecutedMultipleTimes(): void
    {
        $this->testMigration();

        $migration = new Migration1663402950SetDoubleOptinCustomerActive();
        $migration->update($this->connection);
    }

    private function addCustomerWithDoubleOptInButNotConfirmed(string $customerId): int|string
    {
        $customerAddressId = Uuid::randomBytes();
        $now = new \DateTimeImmutable();

        return $this->connection->insert('customer', [
            'id' => $customerId,
            'customer_group_id' => Uuid::fromHexToBytes(TestDefaults::FALLBACK_CUSTOMER_GROUP),
            'default_payment_method_id' => $this->connection->fetchOne('SELECT id FROM `payment_method` WHERE `active` = 1'),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'default_billing_address_id' => $customerAddressId,
            'default_shipping_address_id' => $customerAddressId,
            'customer_number' => '123',
            'first_name' => 'Bar',
            'last_name' => 'Foo',
            'email' => 'foo@bar.com',
            'active' => 0,
            'double_opt_in_registration' => 1,
            'double_opt_in_email_sent_date' => $now->format('Y-m-d H:i:s'),
            'guest' => 0,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    private function checkCustomerIsActive(string $customerId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT active FROM  customer WHERE id = :customerId',
            ['customerId' => $customerId]
        );
    }
}
