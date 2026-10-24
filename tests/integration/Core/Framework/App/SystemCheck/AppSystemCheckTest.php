<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\SystemCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\SystemCheck\AppHealth;
use Shopware\Core\Framework\App\SystemCheck\AppSystemCheck;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class AppSystemCheckTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SystemConfigService $systemConfig;

    private MockClock $clock;

    private AppSystemCheck $check;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->systemConfig = static::getContainer()->get(SystemConfigService::class);
        $this->clock = new MockClock('2026-06-15 12:00:00');
        $this->check = new AppSystemCheck(
            $this->connection,
            static::getContainer()->get(ShopIdProvider::class),
            new ArrayAdapter(),
            $this->clock,
        );

        $this->systemConfig->delete(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2);
    }

    public function testInactiveAppIsSkipped(): void
    {
        $appId = $this->createApp(active: false);

        $this->assertHealth($appId, Status::SKIPPED, AppSystemCheck::REASON_INACTIVE);
    }

    public function testShopIdMismatchIsAnError(): void
    {
        $appId = $this->createApp();
        $this->systemConfig->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, [
            'id' => Uuid::randomHex(),
            'version' => 2,
            'fingerprints' => ['app_url' => 'https://moved-shop.example'],
        ]);

        $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_SHOP_ID_MISMATCH);
    }

    public function testShopIdMismatchIgnoresAppWithoutSecret(): void
    {
        $appWithoutSecretId = $this->createApp(appSecret: null);
        $this->createWebhook($appWithoutSecretId);
        $registeredAppId = $this->createApp();
        $this->systemConfig->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, [
            'id' => Uuid::randomHex(),
            'version' => 2,
            'fingerprints' => ['app_url' => 'https://moved-shop.example'],
        ]);

        $this->assertHealth($appWithoutSecretId, Status::OK, null);
        $this->assertHealth($registeredAppId, Status::ERROR, AppSystemCheck::REASON_SHOP_ID_MISMATCH);
    }

    public function testShopIdV1OnlyConfigIsNotComparedAndNothingIsWritten(): void
    {
        $appId = $this->createApp();
        $this->createWebhook($appId);
        $this->systemConfig->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'value' => Uuid::randomHex(),
            'app_url' => 'https://moved-shop.example',
        ]);
        $before = $this->fetchSystemConfig();

        $this->assertHealth($appId, Status::OK, null);

        static::assertSame($before, $this->fetchSystemConfig());
    }

    public function testStaleUnconfirmedSecretsMeanRegistrationIncomplete(): void
    {
        $appId = $this->createApp(unconfirmedSecrets: true, updatedAt: '-6 minutes');

        $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_REGISTRATION_INCOMPLETE);
    }

    public function testStaleUnconfirmedSecretsOfNeverUpdatedAppMeanRegistrationIncomplete(): void
    {
        $appId = $this->createApp(unconfirmedSecrets: true, createdAt: '-6 minutes', updatedAt: null);

        $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_REGISTRATION_INCOMPLETE);
    }

    public function testFreshUnconfirmedSecretsAreNotYetStuck(): void
    {
        $appId = $this->createApp(unconfirmedSecrets: true, updatedAt: '-4 minutes');
        $this->createWebhook($appId);

        $this->assertHealth($appId, Status::OK, null);
    }

    #[DataProvider('rejectedStatusCodes')]
    public function testRetryRejectedWithCredentialsErrorIsAnError(int $statusCode): void
    {
        $appId = $this->createApp();
        $this->createRetryingDelivery($this->createWebhook($appId), $statusCode);

        $health = $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_CREDENTIALS_REJECTED);
        static::assertSame($statusCode, $health->lastStatusCode);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function rejectedStatusCodes(): iterable
    {
        yield 'unauthorized' => [401];
        yield 'forbidden' => [403];
    }

    public function testQueuedRedeliveryRejectedWithCredentialsErrorIsAnError(): void
    {
        $appId = $this->createApp();
        $this->createRetryingDelivery($this->createWebhook($appId), 401, deliveryStatus: WebhookEventLogDefinition::STATUS_QUEUED);

        $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_CREDENTIALS_REJECTED);
    }

    public function testLatestRetryDecidesOverOlderRejectedCredentials(): void
    {
        $appId = $this->createApp();
        $webhookId = $this->createWebhook($appId);
        $this->createRetryingDelivery($webhookId, 401, lastAttemptAt: '-2 minutes');
        $this->createRetryingDelivery($webhookId, 500, lastAttemptAt: '-1 minute');

        $health = $this->assertHealth($appId, Status::WARNING, AppSystemCheck::REASON_DELIVERY_FAILING);
        static::assertSame(500, $health->lastStatusCode);
    }

    public function testLatestRetryWithRejectedCredentialsDecidesOverOlderFailure(): void
    {
        $appId = $this->createApp();
        $webhookId = $this->createWebhook($appId);
        $this->createRetryingDelivery($webhookId, 500, lastAttemptAt: '-2 minutes');
        $this->createRetryingDelivery($webhookId, 401, lastAttemptAt: '-1 minute');

        $health = $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_CREDENTIALS_REJECTED);
        static::assertSame(401, $health->lastStatusCode);
    }

    public function testRetryWithOtherStatusCodeIsDeliveryFailing(): void
    {
        $appId = $this->createApp();
        $this->createRetryingDelivery($this->createWebhook($appId), 500);

        $health = $this->assertHealth($appId, Status::WARNING, AppSystemCheck::REASON_DELIVERY_FAILING);
        static::assertSame(500, $health->lastStatusCode);
    }

    public function testRetryWithoutResponseIsDeliveryFailing(): void
    {
        $appId = $this->createApp();
        $this->createRetryingDelivery($this->createWebhook($appId), null);

        $health = $this->assertHealth($appId, Status::WARNING, AppSystemCheck::REASON_DELIVERY_FAILING);
        static::assertNull($health->lastStatusCode);
    }

    public function testFirstAttemptNotYetRetriedIsNotCountedAsFailing(): void
    {
        $appId = $this->createApp();
        $this->createRetryingDelivery($this->createWebhook($appId), 401, executionCount: 0);

        $this->assertHealth($appId, Status::OK, null);
    }

    public function testActiveWebhookWithErrorCountIsDeliveryFailing(): void
    {
        $appId = $this->createApp();
        $this->createWebhook($appId, errorCount: 3);

        $this->assertHealth($appId, Status::WARNING, AppSystemCheck::REASON_DELIVERY_FAILING);
    }

    public function testDeactivatedWebhookIsSuspended(): void
    {
        $appId = $this->createApp();
        $this->createWebhook($appId, active: false, errorCount: 10);

        $this->assertHealth($appId, Status::WARNING, AppSystemCheck::REASON_WEBHOOKS_SUSPENDED);
    }

    public function testStaleIntegrationUsageMeansNoAppActivity(): void
    {
        $appId = $this->createApp(lastUsageAt: '-8 days');
        $this->createWebhook($appId);

        $health = $this->assertHealth($appId, Status::WARNING, AppSystemCheck::REASON_NO_APP_ACTIVITY);
        static::assertSame('2026-06-07T12:00:00.000+00:00', $health->lastAppActivityAt);
    }

    public function testRecentIntegrationUsageIsOk(): void
    {
        $appId = $this->createApp(lastUsageAt: '-6 days');
        $this->createWebhook($appId);

        $this->assertHealth($appId, Status::OK, null);
    }

    public function testNoWebhooksAndNoUsageIsUnknown(): void
    {
        $appId = $this->createApp();

        $this->assertHealth($appId, Status::UNKNOWN, AppSystemCheck::REASON_NO_SIGNAL);
    }

    public function testNoWebhooksWithRecentUsageIsOk(): void
    {
        $appId = $this->createApp(lastUsageAt: '-1 day');

        $this->assertHealth($appId, Status::OK, null);
    }

    public function testStuckRegistrationWinsOverFailingWebhookAndStaleActivity(): void
    {
        $appId = $this->createApp(unconfirmedSecrets: true, updatedAt: '-10 minutes', lastUsageAt: '-30 days');
        $this->createRetryingDelivery($this->createWebhook($appId, errorCount: 2), 500);

        $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_REGISTRATION_INCOMPLETE);
    }

    public function testShopIdMismatchWinsOverStuckRegistration(): void
    {
        $appId = $this->createApp(unconfirmedSecrets: true, updatedAt: '-10 minutes');
        $this->systemConfig->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, [
            'id' => Uuid::randomHex(),
            'version' => 2,
            'fingerprints' => ['app_url' => 'https://moved-shop.example'],
        ]);

        $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_SHOP_ID_MISMATCH);
    }

    public function testInactiveWinsOverEverySignal(): void
    {
        $appId = $this->createApp(active: false, unconfirmedSecrets: true, updatedAt: '-10 minutes');
        $this->createRetryingDelivery($this->createWebhook($appId), 401);

        $this->assertHealth($appId, Status::SKIPPED, AppSystemCheck::REASON_INACTIVE);
    }

    public function testRejectedCredentialsWinOverDeliveryFailingAndSuspendedWebhooks(): void
    {
        $appId = $this->createApp();
        $this->createWebhook($appId, active: false);
        $this->createRetryingDelivery($this->createWebhook($appId, errorCount: 2), 401);

        $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_CREDENTIALS_REJECTED);
    }

    public function testRunReportsWorstStatusAndHealthyOnlyForError(): void
    {
        $appId = $this->createApp();
        $this->createWebhook($appId, errorCount: 1);

        $this->createApp(lastUsageAt: '-1 day');

        $result = $this->check->run();

        static::assertSame(Status::WARNING, $result->status);
        static::assertTrue($result->healthy);
        static::assertEquals(['WARNING' => 1, 'OK' => 1], $result->extra);
    }

    public function testRunIsUnhealthyWhenAnAppCannotCommunicate(): void
    {
        $appId = $this->createApp();
        $this->createRetryingDelivery($this->createWebhook($appId), 401);
        $this->createApp(lastUsageAt: '-1 day');

        $result = $this->check->run();

        static::assertSame(Status::ERROR, $result->status);
        static::assertFalse($result->healthy);
        static::assertEquals(['ERROR' => 1, 'OK' => 1], $result->extra);
    }

    public function testRunReportsSkippedWhenTheOnlyAppIsInactive(): void
    {
        $this->createApp(active: false);

        $result = $this->check->run();

        static::assertSame(Status::SKIPPED, $result->status);
        static::assertTrue($result->healthy);
        static::assertSame(['SKIPPED' => 1], $result->extra);
    }

    public function testSecondCollectWithinTtlReturnsCachedResult(): void
    {
        $check = new AppSystemCheck(
            $this->connection,
            static::getContainer()->get(ShopIdProvider::class),
            new ArrayAdapter(),
            $this->clock,
        );
        $appId = $this->createApp(lastUsageAt: '-1 day');

        static::assertSame(Status::OK, $check->collect()[$appId]->status);

        $this->connection->executeStatement('UPDATE app SET active = 0 WHERE id = :id', ['id' => Uuid::fromHexToBytes($appId)]);

        static::assertSame(Status::OK, $check->collect()[$appId]->status);
    }

    private function assertHealth(string $appId, Status $status, ?string $reason): AppHealth
    {
        $apps = $this->check->collect();

        static::assertArrayHasKey($appId, $apps);
        $health = $apps[$appId];
        static::assertSame($status, $health->status);
        static::assertSame($reason, $health->reason);

        return $health;
    }

    /**
     * @return array<string, string|null>
     */
    private function fetchSystemConfig(): array
    {
        /** @var array<string, string|null> $rows */
        $rows = $this->connection->fetchAllKeyValue('SELECT CONCAT(configuration_key, COALESCE(HEX(sales_channel_id), "")), configuration_value FROM system_config');

        return $rows;
    }

    private function createApp(
        bool $active = true,
        ?string $appSecret = 'app-secret',
        bool $unconfirmedSecrets = false,
        string $createdAt = '-1 minute',
        ?string $updatedAt = '-1 minute',
        ?string $lastUsageAt = null,
    ): string {
        $unique = Uuid::randomHex();
        $integrationId = Uuid::randomBytes();
        $appId = Uuid::randomBytes();
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('acl_role', [
            'id' => $aclRoleId = Uuid::randomBytes(),
            'name' => 'role-' . $unique,
            'privileges' => json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        $this->connection->insert('integration', [
            'id' => $integrationId,
            'access_key' => 'key-' . $unique,
            'secret_access_key' => 'secret-' . $unique,
            'label' => 'integration-' . $unique,
            'last_usage_at' => $lastUsageAt !== null ? $this->clock->now()->modify($lastUsageAt)->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'created_at' => $now,
        ]);

        $this->connection->insert('app', [
            'id' => $appId,
            'name' => 'app-' . $unique,
            'path' => '/dev/null',
            'version' => '1.0.0',
            'active' => (int) $active,
            'app_secret' => $appSecret,
            'unconfirmed_app_secrets' => $unconfirmedSecrets ? json_encode(['secret' => 'pending'], \JSON_THROW_ON_ERROR) : null,
            'integration_id' => $integrationId,
            'acl_role_id' => $aclRoleId,
            'created_at' => $this->clock->now()->modify($createdAt)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => $updatedAt !== null ? $this->clock->now()->modify($updatedAt)->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
        ]);

        return Uuid::fromBytesToHex($appId);
    }

    private function createWebhook(string $appId, bool $active = true, int $errorCount = 0): string
    {
        $webhookId = Uuid::randomBytes();

        $this->connection->insert('webhook', [
            'id' => $webhookId,
            'name' => 'hook-' . Uuid::randomHex(),
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'app_id' => Uuid::fromHexToBytes($appId),
            'active' => (int) $active,
            'error_count' => $errorCount,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $webhookId;
    }

    private function createRetryingDelivery(
        string $webhookId,
        ?int $responseStatusCode,
        int $executionCount = 1,
        string $deliveryStatus = WebhookEventLogDefinition::STATUS_PENDING_RETRY,
        string $lastAttemptAt = 'now',
    ): void {
        $eventLogId = Uuid::randomBytes();
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $eventLogId,
            'delivery_status' => $deliveryStatus,
            'webhook_name' => 'hook',
            'event_name' => 'product.written',
            'app_name' => null,
            'url' => 'https://example.com/webhook',
            'response_status_code' => $responseStatusCode,
            'serialized_webhook_message' => serialize('test'),
            'created_at' => $now,
        ]);

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $eventLogId,
            'webhook_id' => $webhookId,
            'partition_key' => Uuid::randomBytes(),
            'delivery_status' => $deliveryStatus,
            'execution_count' => $executionCount,
            'last_attempt_at' => $this->clock->now()->modify($lastAttemptAt)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'next_retry_at' => $now,
            'created_at' => $now,
        ]);
    }
}
