<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseHelper\TestIntegration;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1793709317AddOwnerTypeToWebhook;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793709317AddOwnerTypeToWebhook::class)]
class Migration1793709317AddOwnerTypeToWebhookTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1793709317AddOwnerTypeToWebhook $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1793709317AddOwnerTypeToWebhook();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793709317, $this->migration->getCreationTimestamp());
    }

    public function testExistingWebhooksGetTheTypeOfTheirOwner(): void
    {
        $app = (new AppFixture(KernelLifecycleManager::getKernel()->getContainer()->get('app.repository')))->createAppFromData();
        $integrationId = TestIntegration::create($this->connection)->getId();
        $adminId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM user WHERE username = :username', ['username' => 'admin']);
        static::assertIsString($adminId);

        $appWebhook = $this->insertWebhook(appId: $app->getId());
        $integrationWebhook = $this->insertWebhook(ownerIntegrationId: $integrationId);
        $userWebhook = $this->insertWebhook(ownerUserId: $adminId);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertEquals(
            [$appWebhook => 'app', $integrationWebhook => 'integration', $userWebhook => 'user'],
            $this->connection->fetchAllKeyValue(
                'SELECT LOWER(HEX(id)), owner_type FROM webhook WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList([$appWebhook, $integrationWebhook, $userWebhook])],
                ['ids' => ArrayParameterType::BINARY]
            )
        );
    }

    private function insertWebhook(?string $appId = null, ?string $ownerIntegrationId = null, ?string $ownerUserId = null): string
    {
        $id = Uuid::randomHex();
        $this->connection->insert('webhook', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => 'webhook-' . $id,
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'app_id' => $appId === null ? null : Uuid::fromHexToBytes($appId),
            'owner_integration_id' => $ownerIntegrationId === null ? null : Uuid::fromHexToBytes($ownerIntegrationId),
            'owner_user_id' => $ownerUserId === null ? null : Uuid::fromHexToBytes($ownerUserId),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }
}
