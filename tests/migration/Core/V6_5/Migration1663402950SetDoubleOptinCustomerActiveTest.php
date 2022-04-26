<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1663402950SetDoubleOptinCustomerActive;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1663402950SetDoubleOptinCustomerActive
 */
class Migration1663402950SetDoubleOptinCustomerActiveTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $migration = new Migration1663402950SetDoubleOptinCustomerActive();

        $customerId = Uuid::randomBytes();
        $customerAddressId = Uuid::randomBytes();
        $now = new \DateTimeImmutable();

        $countAffectedRows = $this->connection->insert('customer', [
            'id' => $customerId,
            'customer_group_id' => Uuid::fromHexToBytes(Defaults::FALLBACK_CUSTOMER_GROUP),
            'default_payment_method_id' => $this->connection->fetchOne('SELECT id FROM `payment_method` WHERE `active` = 1'),
            'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
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

        static::assertEquals(1, $countAffectedRows);

        $migration->update($this->connection);

        $result = $this->connection->fetchOne(
            'SELECT active FROM  customer WHERE id = :customerId',
            ['customerId' => $customerId]
        );

        static::assertEquals(1, $result);
    }
}
