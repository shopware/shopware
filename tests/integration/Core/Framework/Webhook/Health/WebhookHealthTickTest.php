<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class WebhookHealthTickTest extends TestCase
{
    use IntegrationTestBehaviour;

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

    public function testTickCancelsAHeldRowAgedPastTheGraceWindowAndReleasesTheNextOldest(): void
    {
        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-old', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-fresh', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->ageDeliveryRow('evt-old', '-25 hours');
        $this->ageDeliveryRow('evt-fresh', '-23 hours');

        $this->service->tick();

        $this->assertDeliveryDeleted('evt-old');
        $this->assertEventLogStatus('evt-old', WebhookEventLogDefinition::STATUS_FAILED);
        $this->assertFailureReason('evt-old', WebhookOutboxStore::CANCEL_REASON_HELD_EXPIRED);

        $this->assertDeliveryStatus('evt-fresh', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-fresh', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
    }

    public function testTickCancelsClaimableRowsBeyondTheOldestOnASuspendedWebhook(): void
    {
        $this->createWebhook('wh-1', active: false, errorCount: 3);
        $this->insertHealth('wh-1', EndpointState::Suspended, cooldownUntil: new \DateTimeImmutable('+1 hour'), nonTransientFailures: 3, suspendedSince: new \DateTimeImmutable('-1 day'));
        $this->createDelivery('evt-oldest', 'wh-1', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->createDelivery('evt-surplus', 'wh-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        $this->service->tick();

        $this->assertDeliveryStatus('evt-oldest', WebhookEventLogDefinition::STATUS_QUEUED);

        $this->assertDeliveryDeleted('evt-surplus');
        $this->assertEventLogStatus('evt-surplus', WebhookEventLogDefinition::STATUS_FAILED);
        $this->assertFailureReason('evt-surplus', WebhookOutboxStore::CANCEL_REASON_SUSPENDED);
    }

    public function testTickRetiresASuspendedWebhookPastTheBoundAndDropsItsWholeBacklog(): void
    {
        $this->createWebhook('wh-old', active: false, errorCount: 3);
        $this->insertHealth('wh-old', EndpointState::Suspended, cooldownUntil: new \DateTimeImmutable('+1 hour'), nonTransientFailures: 3, suspendedSince: new \DateTimeImmutable('-8 days'));
        $this->createDelivery('evt-held', 'wh-old', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-running', 'wh-old', WebhookEventLogDefinition::STATUS_RUNNING);

        $this->createWebhook('wh-young', active: false, errorCount: 3);
        $this->insertHealth('wh-young', EndpointState::Suspended, cooldownUntil: new \DateTimeImmutable('+1 hour'), nonTransientFailures: 3, suspendedSince: new \DateTimeImmutable('-6 days'));

        $this->service->tick();

        static::assertSame(EndpointState::Disabled->value, $this->fetchEndpointState('wh-old'));
        static::assertSame(DisabledOrigin::Escalation->value, $this->fetchDisabledOrigin('wh-old'));
        static::assertNotNull($this->fetchHealthTimestamp('wh-old', 'disabled_since'));
        static::assertNull($this->fetchHealthTimestamp('wh-old', 'cooldown_until'), 'cooldown must be cleared');
        static::assertFalse($this->fetchActive('wh-old'));

        foreach (['evt-held', 'evt-running'] as $eventKey) {
            $this->assertDeliveryDeleted($eventKey);
            $this->assertEventLogStatus($eventKey, WebhookEventLogDefinition::STATUS_FAILED);
            $this->assertFailureReason($eventKey, WebhookOutboxStore::DROP_REASON_DISABLED);
        }

        static::assertSame(EndpointState::Suspended->value, $this->fetchEndpointState('wh-young'));
        static::assertNull($this->fetchHealthTimestamp('wh-young', 'disabled_since'));
    }

    public function testTickShiftsAPausedSuspensionClockSoDeactivatedTimeNeverRetires(): void
    {
        $appId = $this->createApp('SwagShiftApp', active: true);
        $this->createWebhook('wh-1', active: false, errorCount: 3, appId: $appId);
        $this->insertHealth('wh-1', EndpointState::Suspended, cooldownUntil: new \DateTimeImmutable('+1 hour'), nonTransientFailures: 3, suspendedSince: new \DateTimeImmutable('-9 days'));

        $this->connection->executeStatement('UPDATE app SET active = 0 WHERE id = :id', ['id' => Uuid::fromHexToBytes($appId)]);
        $this->service->pauseSuspensionClockForApp($appId);

        $this->connection->executeStatement(
            'UPDATE webhook_health SET updated_at = :cursor WHERE webhook_id = :id',
            [
                'cursor' => (new \DateTimeImmutable('-4 days'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->getBytes('wh-1'),
            ]
        );

        $suspendedSinceBefore = $this->fetchHealthTimestamp('wh-1', 'suspended_since');
        static::assertIsString($suspendedSinceBefore);
        $cursorBefore = $this->fetchHealthTimestamp('wh-1', 'updated_at');
        static::assertIsString($cursorBefore);

        $this->service->tick();

        static::assertSame(EndpointState::Suspended->value, $this->fetchEndpointState('wh-1'));
        $shifted = $this->fetchHealthTimestamp('wh-1', 'suspended_since');
        static::assertIsString($shifted);

        $pausedSeconds = (new \DateTimeImmutable())->getTimestamp() - (new \DateTimeImmutable($cursorBefore))->getTimestamp();
        static::assertEqualsWithDelta(
            (new \DateTimeImmutable($suspendedSinceBefore))->getTimestamp() + $pausedSeconds,
            (new \DateTimeImmutable($shifted))->getTimestamp(),
            90,
            'suspended_since must advance by the paused interval',
        );

        $this->connection->executeStatement('UPDATE app SET active = 1 WHERE id = :id', ['id' => Uuid::fromHexToBytes($appId)]);
        $this->service->tick();

        static::assertSame(EndpointState::Suspended->value, $this->fetchEndpointState('wh-1'));
        static::assertNull($this->fetchHealthTimestamp('wh-1', 'disabled_since'));
    }

    public function testAppLifecycleEventsPauseAndResumeTheSuspensionClock(): void
    {
        $appId = $this->createApp('SwagLifecycleApp', active: true);
        $this->createWebhook('wh-1', active: false, errorCount: 3, appId: $appId);
        $this->insertHealth('wh-1', EndpointState::Suspended, suspendedSince: new \DateTimeImmutable('-9 days'));

        $app = (new AppEntity())->assign(['id' => $appId, 'active' => true]);
        $context = Context::createDefaultContext();
        $cursor = new \DateTimeImmutable('-4 days');
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $suspendedSinceBefore = $this->fetchHealthTimestamp('wh-1', 'suspended_since');
        static::assertIsString($suspendedSinceBefore);

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($app, $appId, $context, $cursor, $eventDispatcher): void {
            $eventDispatcher->dispatch(new AppDeactivatedEvent($app, $context));

            $pausedAt = $this->fetchHealthTimestamp('wh-1', 'updated_at');
            static::assertIsString($pausedAt);
            static::assertEqualsWithDelta(
                (new \DateTimeImmutable())->getTimestamp(),
                (new \DateTimeImmutable($pausedAt))->getTimestamp(),
                5,
            );

            $this->connection->executeStatement(
                'UPDATE app SET active = 0 WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($appId)],
            );
            $this->connection->executeStatement(
                'UPDATE webhook_health SET updated_at = :cursor WHERE webhook_id = :id',
                [
                    'cursor' => $cursor->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'id' => $this->ids->getBytes('wh-1'),
                ],
            );

            $this->connection->executeStatement(
                'UPDATE app SET active = 1 WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($appId)],
            );
            $app->setActive(true);
            $eventDispatcher->dispatch(new AppActivatedEvent($app, $context));
        });

        $shifted = $this->fetchHealthTimestamp('wh-1', 'suspended_since');
        static::assertIsString($shifted);
        $pausedSeconds = (new \DateTimeImmutable())->getTimestamp() - $cursor->getTimestamp();
        static::assertEqualsWithDelta(
            (new \DateTimeImmutable($suspendedSinceBefore))->getTimestamp() + $pausedSeconds,
            (new \DateTimeImmutable($shifted))->getTimestamp(),
            5,
        );
        static::assertSame(EndpointState::Suspended->value, $this->fetchEndpointState('wh-1'));
    }

    private function createApp(string $name, bool $active): string
    {
        $appId = Uuid::randomHex();
        static::getContainer()->get('app.repository')->create([[
            'id' => $appId,
            'name' => $name,
            'path' => 'custom/apps/' . $name,
            'active' => $active,
            'version' => '1.0.0',
            'label' => $name,
            'integration' => ['label' => $name, 'accessKey' => Uuid::randomHex(), 'secretAccessKey' => Uuid::randomHex()],
            'aclRole' => ['name' => $name],
        ]], Context::createDefaultContext());

        return $appId;
    }

    private function createWebhook(string $webhookKey, bool $active = true, int $errorCount = 0, ?string $appId = null): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($webhookKey),
            'name' => $webhookKey,
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'active' => (int) $active,
            'error_count' => $errorCount,
            'app_id' => $appId !== null ? Uuid::fromHexToBytes($appId) : null,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function insertHealth(
        string $webhookKey,
        EndpointState $state,
        ?\DateTimeImmutable $cooldownUntil = null,
        int $transientFailures = 0,
        int $degradedCycleCount = 0,
        int $nonTransientFailures = 0,
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

    private function ageDeliveryRow(string $eventKey, string $modifier): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET created_at = :createdAt WHERE webhook_event_log_id = :id',
            [
                'createdAt' => (new \DateTimeImmutable($modifier))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->getBytes($eventKey),
            ]
        );
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

    private function assertFailureReason(string $eventKey, string $expectedReason): void
    {
        $reason = $this->connection->fetchOne(
            'SELECT failure_reason FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertSame($expectedReason, $reason);
    }

    private function assertDeliveryDeleted(string $eventKey): void
    {
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertFalse($exists);
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

    private function fetchDisabledOrigin(string $webhookKey): ?string
    {
        $value = $this->connection->fetchOne(
            'SELECT disabled_origin FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($webhookKey)]
        );

        return \is_string($value) ? $value : null;
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
