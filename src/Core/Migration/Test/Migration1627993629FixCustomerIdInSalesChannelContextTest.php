<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1627993629FixCustomerIdInSalesChannelContext;

class Migration1627993629FixCustomerIdInSalesChannelContextTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->connection->executeStatement('DELETE FROM sales_channel_api_context');

        // disable fk check to prevent creating a lot of overhead data
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // disable fk check to prevent creating a lot of overhead data
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testCustomerIdWillBeSetFromPayload(): void
    {
        $customerId = '5bebaf06cc004790bb5d9fa2f4325b62';

        $this->connection->insert('sales_channel_api_context', [
            'token' => '4s9wdUx856S2lW6AOJhm9qYaTyGIv0Wq',
            'payload' => "{\"expired\": false, \"customerId\": \"$customerId\", \"billingAddressId\": null, \"shippingAddressId\": null}",
            'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
            'customer_id' => null,
        ]);

        $migration = new Migration1627993629FixCustomerIdInSalesChannelContext();
        $migration->update($this->connection);

        $actualCustomerId = $this->connection->fetchOne('SELECT customer_id FROM sales_channel_api_context LIMIT 1');

        static::assertNotNull($actualCustomerId);
        static::assertSame($customerId, Uuid::fromBytesToHex($actualCustomerId));
    }

    public function testMigrationWorksWithCustomerIdNotBeingInPayload(): void
    {
        $this->connection->insert('sales_channel_api_context', [
            'token' => '4s9wdUx856S2lW6AOJhm9qYaTyGIv0Wq',
            'payload' => '{"expired": false, "billingAddressId": null, "shippingAddressId": null}',
            'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
            'customer_id' => null,
        ]);

        $migration = new Migration1627993629FixCustomerIdInSalesChannelContext();
        $migration->update($this->connection);

        $customerId = $this->connection->fetchOne('SELECT customer_id FROM sales_channel_api_context LIMIT 1');

        static::assertNull($customerId);
    }

    public function testMigrationWillNotOverrideCustomerIdsThatAreAlreadySet(): void
    {
        $this->connection->insert('sales_channel_api_context', [
            'token' => '4s9wdUx856S2lW6AOJhm9qYaTyGIv0Wq',
            'payload' => '{"expired": false, "customerId": "5bebaf06cc004790bb5d9fa2f4325b62", "billingAddressId": null, "shippingAddressId": null}',
            'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
            'customer_id' => Uuid::fromHexToBytes('5bebaf06cc004790bb5d9fa2f4325b61'),
        ]);

        $migration = new Migration1627993629FixCustomerIdInSalesChannelContext();
        $migration->update($this->connection);

        $customerId = $this->connection->fetchOne('SELECT customer_id FROM sales_channel_api_context LIMIT 1');

        static::assertSame('5bebaf06cc004790bb5d9fa2f4325b61', Uuid::fromBytesToHex($customerId));
    }
}
