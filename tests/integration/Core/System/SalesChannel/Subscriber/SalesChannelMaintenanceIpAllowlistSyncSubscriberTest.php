<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[DisabledFeatures(['v6.8.0.0'])]
class SalesChannelMaintenanceIpAllowlistSyncSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->repository = static::getContainer()->get('sales_channel.repository');
    }

    public function testWritingNewAllowlistViaApiSyncsDeprecatedWhitelist(): void
    {
        $id = Uuid::randomHex();
        $this->createSalesChannel([
            'id' => $id,
            'maintenanceIpAllowlist' => ['127.0.0.1', '::1'],
        ]);

        $this->assertColumns($id, ['127.0.0.1', '::1'], ['127.0.0.1', '::1']);
    }

    public function testWritingDeprecatedWhitelistViaApiSyncsNewAllowlist(): void
    {
        $id = Uuid::randomHex();
        $this->createSalesChannel([
            'id' => $id,
            'maintenanceIpWhitelist' => ['10.0.0.1'],
        ]);

        $this->assertColumns($id, ['10.0.0.1'], ['10.0.0.1']);
    }

    public function testNewAllowlistWinsWhenBothAreWrittenViaApi(): void
    {
        $id = Uuid::randomHex();
        $this->createSalesChannel([
            'id' => $id,
            'maintenanceIpAllowlist' => ['127.0.0.1'],
            'maintenanceIpWhitelist' => ['10.0.0.1'],
        ]);

        $this->assertColumns($id, ['127.0.0.1'], ['127.0.0.1']);
    }

    public function testUpdatingNewAllowlistViaApiSyncsDeprecatedWhitelist(): void
    {
        $id = Uuid::randomHex();
        $this->createSalesChannel([
            'id' => $id,
            'maintenanceIpAllowlist' => ['127.0.0.1'],
        ]);

        $this->repository->update([[
            'id' => $id,
            'maintenanceIpAllowlist' => ['192.168.0.1'],
        ]], Context::createDefaultContext());

        $this->assertColumns($id, ['192.168.0.1'], ['192.168.0.1']);
    }

    /**
     * @param list<string> $expectedAllowlist
     * @param list<string> $expectedWhitelist
     */
    private function assertColumns(string $id, array $expectedAllowlist, array $expectedWhitelist): void
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT maintenance_ip_allowlist, maintenance_ip_whitelist
                FROM sales_channel
                WHERE id = :id
            SQL,
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertIsArray($row);
        static::assertSame(
            $expectedAllowlist,
            json_decode((string) $row['maintenance_ip_allowlist'], true, 512, \JSON_THROW_ON_ERROR)
        );
        static::assertSame(
            $expectedWhitelist,
            json_decode((string) $row['maintenance_ip_whitelist'], true, 512, \JSON_THROW_ON_ERROR)
        );
    }
}
