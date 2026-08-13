<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1786627139BackfillSalesChannelApiContextCustomerId;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1786627139BackfillSalesChannelApiContextCustomerId::class)]
class Migration1786627139BackfillSalesChannelApiContextCustomerIdTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1786627139, (new Migration1786627139BackfillSalesChannelApiContextCustomerId())->getCreationTimestamp());
    }

    public function testBackfillsMissingCustomerIdAndKeepsExistingPayloadValue(): void
    {
        $emptyArrayCustomerId = $this->createCustomer();
        $existingCustomerId = $this->createCustomer();
        $emptyArrayToken = Uuid::randomHex();
        $existingToken = Uuid::randomHex();
        $keptCustomerId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $this->insertContext($emptyArrayToken, $emptyArrayCustomerId, '[]');
        $this->insertContext($existingToken, $existingCustomerId, json_encode(['customerId' => $keptCustomerId], \JSON_THROW_ON_ERROR));

        $migration = new Migration1786627139BackfillSalesChannelApiContextCustomerId();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $emptyArrayPayload = $this->fetchPayload($emptyArrayToken);
        static::assertSame($emptyArrayCustomerId, $emptyArrayPayload['customerId']);

        $existingPayload = $this->fetchPayload($existingToken);
        static::assertSame($keptCustomerId, $existingPayload['customerId']);
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $salutationId = Uuid::fromBytesToHex((string) $this->connection->fetchOne('SELECT `id` FROM `salutation` LIMIT 1'));
        $countryId = Uuid::fromBytesToHex((string) $this->connection->fetchOne('SELECT `id` FROM `country` LIMIT 1'));

        KernelLifecycleManager::getKernel()->getContainer()->get('customer.repository')->create([
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $salutationId,
                    'countryId' => $countryId,
                ],
                'defaultBillingAddressId' => $addressId,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $customerId . '@example.com',
                'password' => TestDefaults::HASHED_PASSWORD,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'guest' => false,
                'salutationId' => $salutationId,
                'customerNumber' => $customerId,
            ],
        ], Context::createDefaultContext());

        return $customerId;
    }

    private function insertContext(string $token, string $customerId, string $payload): void
    {
        $this->connection->insert('sales_channel_api_context', [
            'token' => $token,
            'payload' => $payload,
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPayload(string $token): array
    {
        $payload = $this->connection->fetchOne(
            'SELECT `payload` FROM `sales_channel_api_context` WHERE `token` = :token',
            ['token' => $token]
        );

        static::assertIsString($payload);

        $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }
}
