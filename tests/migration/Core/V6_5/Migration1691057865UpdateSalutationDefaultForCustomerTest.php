<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1691057865UpdateSalutationDefaultForCustomer;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1691057865UpdateSalutationDefaultForCustomer::class)]
class Migration1691057865UpdateSalutationDefaultForCustomerTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1691057865UpdateSalutationDefaultForCustomer $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1691057865UpdateSalutationDefaultForCustomer();
    }

    public function testUpdate(): void
    {
        $notSpecified = $this->getNotSpecifiedSalutation();

        $customerId = Uuid::randomBytes();

        $this->addCustomerWithNoneSalutation($customerId);

        $salutationId = $this->connection->fetchOne('SELECT salutation_id FROM customer WHERE id = :customerId', ['customerId' => $customerId]);

        static::assertNull($salutationId);

        $this->migration->update($this->connection);

        $salutationId = $this->connection->fetchOne('SELECT salutation_id FROM customer WHERE id = :customerId', ['customerId' => $customerId]);

        static::assertSame($salutationId, $notSpecified);

        $this->migration->update($this->connection);

        $salutationId = $this->connection->fetchOne('SELECT salutation_id FROM customer WHERE id = :customerId', ['customerId' => $customerId]);

        static::assertSame($salutationId, $notSpecified);
    }

    public function testUpdateWithoutNotSpecifiedKey(): void
    {
        $customerId = Uuid::randomBytes();

        $this->addCustomerWithNoneSalutation($customerId);

        $this->connection->executeStatement(
            '
			DELETE FROM salutation WHERE salutation_key = :salutationKey
		',
            ['salutationKey' => SalutationDefinition::NOT_SPECIFIED]
        );

        $notSpecified = $this->getNotSpecifiedSalutation();

        static::assertSame($notSpecified, '');

        $this->migration->update($this->connection);

        $salutationId = $this->connection->fetchOne('SELECT salutation_id FROM customer WHERE id = :customerId', ['customerId' => $customerId]);

        static::assertNull($salutationId);
    }

    private function addCustomerWithNoneSalutation(string $customerId): int|string
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

    private function getNotSpecifiedSalutation(): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT id FROM salutation WHERE salutation_key = :salutationKey LIMIT 1',
            ['salutationKey' => SalutationDefinition::NOT_SPECIFIED]
        );
    }
}
