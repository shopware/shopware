<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class WebhookHealthTickTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const COOLDOWN_TIER_1 = 600;

    private Connection $connection;

    private WebhookHealthService $service;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->service = static::getContainer()->get(WebhookHealthService::class);
    }

    public function testTickReleasesTheOldestHeldRowWhenTheCooldownHasElapsed(): void
    {
        $this->createWebhook('wh-due');
        $this->insertHealth(
            'wh-due',
            EndpointState::Degraded,
            cooldownUntil: new \DateTimeImmutable('-1 hour'),
            transientFailures: 5,
            degradedCycleCount: 3,
        );
        $this->createDelivery('evt-1', 'wh-due', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-2', 'wh-due', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->tick();
        $this->service->tick();

        $this->assertDeliveryStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertNextRetryAtIsNow('evt-1');
        $this->assertDeliveryStatus('evt-2', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertEventLogStatus('evt-2', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-due'));
        static::assertSame(3, $this->fetchDegradedCycleCount('wh-due'));
    }

    public function testTickDoesNotReleaseBeforeTheCooldownExpires(): void
    {
        $this->createWebhook('wh-cooling');
        $this->insertHealth('wh-cooling', EndpointState::Degraded, new \DateTimeImmutable('+1 hour'));
        $this->createDelivery('evt-cooling', 'wh-cooling', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->tick();

        $this->assertDeliveryStatus('evt-cooling', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-cooling'));
    }

    public function testTickNoOpsWhileAReleaseIsInFlight(): void
    {
        $this->createWebhook('wh-claimable');
        $this->insertHealth('wh-claimable', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-trial-claimable', 'wh-claimable', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->createDelivery('evt-held-claimable', 'wh-claimable', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->createWebhook('wh-running');
        $this->insertHealth('wh-running', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-trial-running', 'wh-running', WebhookEventLogDefinition::STATUS_RUNNING);
        $this->createDelivery('evt-held-running', 'wh-running', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->tick();

        $this->assertDeliveryStatus('evt-held-claimable', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertDeliveryStatus('evt-held-running', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-claimable'));
        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-running'));
    }

    public function testCrashedDegradedTrialRecoversAndItsLateSuccessStillPromotes(): void
    {
        $outboxStore = static::getContainer()->get(WebhookOutboxStore::class);

        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-trial', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-held', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->tick();
        $this->assertDeliveryStatus('evt-trial', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        static::assertNotNull($outboxStore->markRunning($this->ids->get('evt-trial')));

        $this->service->tick();
        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-1'));

        $outboxStore->resetRunningForPartition(Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128'), 0);
        $this->assertDeliveryStatus('evt-trial', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        $entry = $outboxStore->markRunning($this->ids->get('evt-trial'));
        static::assertNotNull($entry);
        static::assertTrue($outboxStore->markSuccess($entry, null));
        $this->service->recordSuccess($this->ids->get('wh-1'));

        static::assertSame(EndpointState::Healthy->value, $this->fetchEndpointState('wh-1'));
        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertNextRetryAtIsNow('evt-held');
    }

    public function testTickIdlePromotesAnIdleDegradedWebhookKeepingTheFailureStreaks(): void
    {
        $this->createWebhook('wh-1', active: false, errorCount: 7);
        $this->insertHealth(
            'wh-1',
            EndpointState::Degraded,
            cooldownUntil: new \DateTimeImmutable('-1 hour'),
            transientFailures: 7,
            degradedCycleCount: 3,
        );

        $this->service->tick();

        static::assertSame(EndpointState::Healthy->value, $this->fetchEndpointState('wh-1'));
        static::assertSame(7, $this->fetchTransientFailures('wh-1'));
        static::assertSame(0, $this->fetchDegradedCycleCount('wh-1'));
        static::assertNull($this->fetchHealthTimestamp('wh-1', 'cooldown_until'));
        static::assertTrue($this->fetchActive('wh-1'));
        static::assertSame(0, $this->fetchErrorCount('wh-1'));
    }

    public function testTickNeverIdlePromotesWhileTheReleasedTrialIsPending(): void
    {
        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-1', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->tick();
        $this->assertDeliveryStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        $this->service->tick();

        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-1'));
    }

    public function testAReleasedTrialsTransientFailureAdvancesTheLadderAtTheResult(): void
    {
        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-1', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);
        $cooldownAtRelease = $this->fetchHealthTimestamp('wh-1', 'cooldown_until');

        $this->service->tick();

        static::assertSame(0, $this->fetchDegradedCycleCount('wh-1'));
        static::assertSame($cooldownAtRelease, $this->fetchHealthTimestamp('wh-1', 'cooldown_until'));

        $result = $this->service->recordFailure($this->ids->get('wh-1'), ErrorClassification::TransientServer, 1, $this->claimRunningEntry('evt-1'));

        static::assertSame(EndpointState::Degraded, $result);
        static::assertSame(1, $this->fetchDegradedCycleCount('wh-1'));
        $this->assertCooldownAbout('wh-1', self::COOLDOWN_TIER_1);
    }

    public function testAReleasedTrialsSuccessPromotesToHealthyAndResumesTheHeldBacklog(): void
    {
        $this->createWebhook('wh-1', active: false, errorCount: 5);
        $this->insertHealth('wh-1', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-trial', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-held', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->tick();
        $this->assertDeliveryStatus('evt-trial', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        $this->service->recordSuccess($this->ids->get('wh-1'));

        static::assertSame(EndpointState::Healthy->value, $this->fetchEndpointState('wh-1'));
        static::assertSame(0, $this->fetchTransientFailures('wh-1'));
        static::assertNull($this->fetchHealthTimestamp('wh-1', 'cooldown_until'));

        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-held', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertNextRetryAtIsNow('evt-held');

        static::assertTrue($this->fetchActive('wh-1'));
        static::assertSame(0, $this->fetchErrorCount('wh-1'));
    }

    public function testTickCleansUpStrandedAndOrphanedHeldRows(): void
    {
        $this->createWebhook('stranded');
        $this->insertHealth('stranded', EndpointState::Healthy);
        $this->createDelivery('evt-stranded', 'stranded', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->createWebhook('orphaned');
        $this->createDelivery('evt-orphaned', 'orphaned', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->connection->delete('webhook', ['id' => $this->ids->getBytes('orphaned')]);

        $this->service->tick();

        $this->assertDeliveryStatus('evt-stranded', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-stranded', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertNextRetryAtIsNow('evt-stranded');

        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-orphaned')]
        ));
        $this->assertEventLogStatus('evt-orphaned', WebhookEventLogDefinition::STATUS_FAILED);
        static::assertSame(WebhookOutboxStore::CANCEL_REASON_ORPHANED, $this->connection->fetchOne(
            'SELECT failure_reason FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-orphaned')]
        ));
    }

    private function createWebhook(string $webhookKey, bool $active = true, int $errorCount = 0, ?string $appId = null): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($webhookKey),
            'name' => $webhookKey,
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'app_id' => $appId !== null ? Uuid::fromHexToBytes($appId) : null,
            'active' => (int) $active,
            'error_count' => $errorCount,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function insertHealth(
        string $webhookKey,
        EndpointState $state,
        ?\DateTimeImmutable $cooldownUntil = null,
        int $transientFailures = 0,
        int $nonTransientFailures = 0,
        int $degradedCycleCount = 0,
        ?\DateTimeImmutable $suspendedSince = null,
    ): void {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($webhookKey),
            'endpoint_state' => $state->value,
            'consecutive_transient_failures' => $transientFailures,
            'consecutive_non_transient_failures' => $nonTransientFailures,
            'degraded_cycle_count' => $degradedCycleCount,
            'cooldown_until' => $cooldownUntil?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'suspended_since' => $suspendedSince?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createDelivery(string $eventKey, string $webhookKey, string $deliveryStatus): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => $deliveryStatus,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $now,
        ]);

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => $this->ids->getBytes($webhookKey),
            'partition_key' => Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128'),
            'delivery_status' => $deliveryStatus,
            'execution_count' => $deliveryStatus === WebhookEventLogDefinition::STATUS_RUNNING ? 1 : 0,
            'last_attempt_at' => $deliveryStatus === WebhookEventLogDefinition::STATUS_RUNNING ? $now : null,
            'created_at' => $now,
        ]);
    }

    private function claimRunningEntry(string $eventKey): OutboxEntry
    {
        $entry = static::getContainer()->get(WebhookOutboxStore::class)->markRunning($this->ids->get($eventKey));
        static::assertNotNull($entry, 'the seeded row must be claimable for the worker');

        return $entry;
    }

    private function assertDeliveryStatus(string $eventKey, string $expectedStatus): void
    {
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertSame($expectedStatus, $status);
    }

    private function assertEventLogStatus(string $eventKey, string $expectedStatus): void
    {
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertSame($expectedStatus, $status);
    }

    private function assertNextRetryAtIsNow(string $eventKey): void
    {
        $nextRetryAt = $this->connection->fetchOne(
            'SELECT next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertIsString($nextRetryAt, 'a released row is due now, so next_retry_at is set');
        static::assertEqualsWithDelta(
            (new \DateTimeImmutable())->getTimestamp(),
            (new \DateTimeImmutable($nextRetryAt))->getTimestamp(),
            5,
        );
    }

    private function assertCooldownAbout(string $webhookKey, int $secondsFromNow): void
    {
        $cooldownUntil = $this->fetchHealthTimestamp($webhookKey, 'cooldown_until');
        static::assertIsString($cooldownUntil, 'cooldown_until must be set');
        static::assertEqualsWithDelta(
            (new \DateTimeImmutable())->getTimestamp() + $secondsFromNow,
            (new \DateTimeImmutable($cooldownUntil))->getTimestamp(),
            30,
            \sprintf('cooldown_until must be about %d seconds from now', $secondsFromNow),
        );
    }

    private function fetchEndpointState(string $webhookKey): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT endpoint_state FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($webhookKey)]
        );
    }

    private function fetchTransientFailures(string $webhookKey): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT consecutive_transient_failures FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($webhookKey)]
        );
    }

    private function fetchDegradedCycleCount(string $webhookKey): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT degraded_cycle_count FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($webhookKey)]
        );
    }

    private function fetchHealthTimestamp(string $webhookKey, string $column): ?string
    {
        $value = $this->connection->fetchOne(
            \sprintf('SELECT %s FROM webhook_health WHERE webhook_id = :id', $column),
            ['id' => $this->ids->getBytes($webhookKey)]
        );

        return \is_string($value) ? $value : null;
    }

    private function fetchActive(string $webhookKey): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT active FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($webhookKey)]
        );
    }

    private function fetchErrorCount(string $webhookKey): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT error_count FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($webhookKey)]
        );
    }
}
