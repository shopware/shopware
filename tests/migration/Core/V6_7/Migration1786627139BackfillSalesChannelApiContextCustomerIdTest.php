<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
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

    /**
     * @var list<string>
     */
    private array $tokens = [];

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->tokens = [];
    }

    protected function tearDown(): void
    {
        if ($this->tokens === []) {
            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM `sales_channel_api_context` WHERE `token` IN (:tokens)',
            ['tokens' => $this->tokens],
            ['tokens' => ArrayParameterType::STRING]
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1786627139, (new Migration1786627139BackfillSalesChannelApiContextCustomerId())->getCreationTimestamp());
    }

    public function testAlignsPayloadCustomerIdWithoutChangingIdentityColumns(): void
    {
        $emptyCustomerId = $this->createCustomer();
        $mismatchCustomerId = $this->createCustomer();
        $syncedCustomerId = $this->createCustomer();
        $emptyToken = Uuid::randomHex();
        $mismatchToken = Uuid::randomHex();
        $syncedToken = Uuid::randomHex();
        $staleCustomerId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $currencyId = Defaults::CURRENCY;

        $this->insertContext($emptyToken, $emptyCustomerId, '[]');
        $this->insertContext(
            $mismatchToken,
            $mismatchCustomerId,
            json_encode(['customerId' => $staleCustomerId, 'currencyId' => $currencyId], \JSON_THROW_ON_ERROR)
        );
        $this->insertContext(
            $syncedToken,
            $syncedCustomerId,
            json_encode(['customerId' => $syncedCustomerId], \JSON_THROW_ON_ERROR)
        );

        $emptyBefore = $this->fetchRow($emptyToken);
        $mismatchBefore = $this->fetchRow($mismatchToken);
        $syncedBefore = $this->fetchRow($syncedToken);

        $migration = new Migration1786627139BackfillSalesChannelApiContextCustomerId();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $emptyAfter = $this->fetchRow($emptyToken);
        $this->assertIdentityUnchanged($emptyBefore, $emptyAfter);
        static::assertSame($emptyCustomerId, $this->decodePayload($emptyAfter['payload'])['customerId']);

        $mismatchAfter = $this->fetchRow($mismatchToken);
        $this->assertIdentityUnchanged($mismatchBefore, $mismatchAfter);
        $mismatchPayload = $this->decodePayload($mismatchAfter['payload']);
        static::assertSame($mismatchCustomerId, $mismatchPayload['customerId']);
        static::assertSame($currencyId, $mismatchPayload['currencyId']);

        $syncedAfter = $this->fetchRow($syncedToken);
        $this->assertIdentityUnchanged($syncedBefore, $syncedAfter);
        static::assertSame($syncedBefore['payload'], $syncedAfter['payload']);
    }

    public function testSkipsGuestRows(): void
    {
        $guestToken = Uuid::randomHex();
        $guestPayload = json_encode(['currencyId' => Defaults::CURRENCY], \JSON_THROW_ON_ERROR);
        $this->insertContext($guestToken, null, $guestPayload);

        $before = $this->fetchRow($guestToken);

        $migration = new Migration1786627139BackfillSalesChannelApiContextCustomerId();
        $migration->update($this->connection);

        $after = $this->fetchRow($guestToken);
        static::assertNull($after['customer_id']);
        static::assertSame($before['token'], $after['token']);
        static::assertSame($before['sales_channel_id'], $after['sales_channel_id']);
        static::assertSame($before['payload'], $after['payload']);
    }

    public function testBackfillsAcrossChunks(): void
    {
        $expected = [];
        for ($i = 0; $i < 5; ++$i) {
            $customerId = $this->createCustomer();
            $token = Uuid::randomHex();
            $expected[$token] = $customerId;
            $this->insertContext($token, $customerId, '[]');
        }

        $migration = new class extends Migration1786627139BackfillSalesChannelApiContextCustomerId {
            protected function getUpdateLimit(): int
            {
                return 2;
            }
        };
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach ($expected as $token => $customerId) {
            static::assertSame($customerId, $this->decodePayload($this->fetchRow($token)['payload'])['customerId']);
        }
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

    private function insertContext(string $token, ?string $customerId, string $payload): void
    {
        $this->tokens[] = $token;

        $this->connection->insert('sales_channel_api_context', [
            'token' => $token,
            'payload' => $payload,
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'customer_id' => $customerId !== null ? Uuid::fromHexToBytes($customerId) : null,
            'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return array{token: string, payload: string, customer_id: string|null, sales_channel_id: string}
     */
    private function fetchRow(string $token): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `token`, `payload`, `customer_id`, `sales_channel_id` FROM `sales_channel_api_context` WHERE `token` = :token',
            ['token' => $token]
        );

        static::assertIsArray($row);
        static::assertIsString($row['token']);
        static::assertIsString($row['payload']);
        static::assertIsString($row['sales_channel_id']);

        $customerId = $row['customer_id'];
        if ($customerId !== null) {
            static::assertIsString($customerId);
        }

        return [
            'token' => $row['token'],
            'payload' => $row['payload'],
            'customer_id' => $customerId,
            'sales_channel_id' => $row['sales_channel_id'],
        ];
    }

    /**
     * @param array{token: string, payload: string, customer_id: string|null, sales_channel_id: string} $before
     * @param array{token: string, payload: string, customer_id: string|null, sales_channel_id: string} $after
     */
    private function assertIdentityUnchanged(array $before, array $after): void
    {
        static::assertSame($before['token'], $after['token']);
        static::assertSame($before['customer_id'], $after['customer_id']);
        static::assertSame($before['sales_channel_id'], $after['sales_channel_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }
}
