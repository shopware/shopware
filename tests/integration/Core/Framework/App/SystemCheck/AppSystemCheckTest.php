<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\SystemCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\SystemCheck\AppSystemCheck;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class AppSystemCheckTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private MockClock $clock;

    private AppSystemCheck $check;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock('2026-06-15 12:00:00');
        $this->check = new AppSystemCheck(
            $this->connection,
            $this->clock,
        );
    }

    public function testInactiveAppIsSkipped(): void
    {
        $appId = $this->createApp(active: false);

        $this->assertHealth($appId, Status::SKIPPED, AppSystemCheck::REASON_INACTIVE);
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
        static::assertSame($statusCode, $health['lastStatusCode']);
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
        static::assertSame(500, $health['lastStatusCode']);
    }

    public function testLatestRetryWithRejectedCredentialsDecidesOverOlderFailure(): void
    {
        $appId = $this->createApp();
        $webhookId = $this->createWebhook($appId);
        $this->createRetryingDelivery($webhookId, 500, lastAttemptAt: '-2 minutes');
        $this->createRetryingDelivery($webhookId, 401, lastAttemptAt: '-1 minute');

        $health = $this->assertHealth($appId, Status::ERROR, AppSystemCheck::REASON_CREDENTIALS_REJECTED);
        static::assertSame(401, $health['lastStatusCode']);
    }

    public function testRetryWithOtherStatusCodeIsDeliveryFailing(): void
    {
        $appId = $this->createApp();
        $this->createRetryingDelivery($this->createWebhook($appId), 500);

        $health = $this->assertHealth($appId, Status::WARNING, AppSystemCheck::REASON_DELIVERY_FAILING);
        static::assertSame(500, $health['lastStatusCode']);
    }

    public function testRetryWithoutResponseIsDeliveryFailing(): void
    {
        $appId = $this->createApp();
        $this->createRetryingDelivery($this->createWebhook($appId), null);

        $health = $this->assertHealth($appId, Status::WARNING, AppSystemCheck::REASON_DELIVERY_FAILING);
        static::assertNull($health['lastStatusCode']);
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

        $this->assertHealth($appId, Status::WARNING, AppSystemCheck::REASON_NO_APP_ACTIVITY);
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
        static::assertEqualsCanonicalizing(['WARNING', 'OK'], array_column($result->extra['apps'], 'status'));
    }

    public function testRunIsUnhealthyWhenAnAppCannotCommunicate(): void
    {
        $appId = $this->createApp();
        $this->createRetryingDelivery($this->createWebhook($appId), 401);
        $this->createApp(lastUsageAt: '-1 day');

        $result = $this->check->run();

        static::assertSame(Status::ERROR, $result->status);
        static::assertFalse($result->healthy);
        static::assertEqualsCanonicalizing(['ERROR', 'OK'], array_column($result->extra['apps'], 'status'));
    }

    public function testRunReportsSkippedWhenTheOnlyAppIsInactive(): void
    {
        $this->createApp(active: false);

        $result = $this->check->run();

        static::assertSame(Status::SKIPPED, $result->status);
        static::assertTrue($result->healthy);
        static::assertSame(['SKIPPED'], array_column($result->extra['apps'], 'status'));
    }

    /**
     * @return array<string, mixed>
     */
    private function assertHealth(string $appId, Status $status, ?string $reason): array
    {
        $name = $this->connection->fetchOne('SELECT name FROM app WHERE id = :id', ['id' => Uuid::fromHexToBytes($appId)]);
        $apps = array_column($this->check->run()->extra['apps'], null, 'name');

        static::assertArrayHasKey($name, $apps);
        static::assertSame($status->name, $apps[$name]['status']);
        static::assertSame($reason, $apps[$name]['reason']);

        return $apps[$name];
    }

    private function createApp(
        bool $active = true,
        ?string $appSecret = 'app-secret',
        bool $unconfirmedSecrets = false,
        string $createdAt = '-1 minute',
        ?string $updatedAt = '-1 minute',
        ?string $lastUsageAt = null,
    ): string {
        $appId = (new AppFixture(static::getContainer()->get('app.repository')))
            ->createAppFromData(['active' => $active, 'appSecret' => $appSecret])
            ->getId();

        // timestamps and pending secrets the repository does not let a test set
        $this->connection->executeStatement(
            'UPDATE app
             INNER JOIN integration ON integration.id = app.integration_id
             SET app.unconfirmed_app_secrets = :unconfirmed, app.created_at = :createdAt, app.updated_at = :updatedAt, integration.last_usage_at = :lastUsageAt
             WHERE app.id = :id',
            [
                'id' => Uuid::fromHexToBytes($appId),
                'unconfirmed' => $unconfirmedSecrets ? json_encode(['secret' => 'pending'], \JSON_THROW_ON_ERROR) : null,
                'createdAt' => $this->date($createdAt),
                'updatedAt' => $updatedAt !== null ? $this->date($updatedAt) : null,
                'lastUsageAt' => $lastUsageAt !== null ? $this->date($lastUsageAt) : null,
            ],
        );

        return $appId;
    }

    private function date(string $modifier): string
    {
        return $this->clock->now()->modify($modifier)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function createWebhook(string $appId, bool $active = true, int $errorCount = 0): string
    {
        $webhookId = Uuid::randomHex();

        static::getContainer()->get('webhook.repository')->create([[
            'id' => $webhookId,
            'name' => 'hook-' . $webhookId,
            'eventName' => 'product.written',
            'url' => 'https://example.com/webhook',
            'appId' => $appId,
            'active' => $active,
            'errorCount' => $errorCount,
        ]], Context::createDefaultContext());

        return Uuid::fromHexToBytes($webhookId);
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
