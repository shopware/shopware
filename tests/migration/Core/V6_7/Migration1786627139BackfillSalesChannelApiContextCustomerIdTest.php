<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
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
        $customerIds = $this->connection->fetchFirstColumn('SELECT `id` FROM `customer` LIMIT 2');
        static::assertCount(2, $customerIds);

        $emptyArrayToken = Uuid::randomHex();
        $existingToken = Uuid::randomHex();
        $keptCustomerId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $this->connection->executeStatement(
            'DELETE FROM `sales_channel_api_context` WHERE `customer_id` IN (:ids)',
            ['ids' => $customerIds],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->insertContext($emptyArrayToken, $customerIds[0], '[]');
        $this->insertContext($existingToken, $customerIds[1], json_encode(['customerId' => $keptCustomerId], \JSON_THROW_ON_ERROR));

        $migration = new Migration1786627139BackfillSalesChannelApiContextCustomerId();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $emptyArrayPayload = $this->fetchPayload($emptyArrayToken);
        static::assertSame(Uuid::fromBytesToHex($customerIds[0]), $emptyArrayPayload['customerId']);

        $existingPayload = $this->fetchPayload($existingToken);
        static::assertSame($keptCustomerId, $existingPayload['customerId']);
    }

    private function insertContext(string $token, string $customerId, string $payload): void
    {
        $this->connection->insert('sales_channel_api_context', [
            'token' => $token,
            'payload' => $payload,
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'customer_id' => $customerId,
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
