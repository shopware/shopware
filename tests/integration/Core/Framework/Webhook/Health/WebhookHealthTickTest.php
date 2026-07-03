<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\EndpointHealth;
use Shopware\Core\Framework\Webhook\Health\EndpointLifecycle;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Drives the health tick's clocked duty — {@see EndpointLifecycle::tick()}, pulsed in production by
 * {@see \Shopware\Core\Framework\Webhook\Health\WebhookHealthTick} on the delivery worker's transport poll — against the real container
 * services, on the row-state contract of the half-open ladder (ADR §Half-open recovery):
 * per DEGRADED webhook with an elapsed cooldown, release the oldest held row as the one trial
 * (grace-age filtered), no-op while a release is in flight, and idle-promote when nothing is held
 * and nothing is in flight — keeping the failure streaks, because nothing was delivered to prove
 * health. Releasing never advances the ladder; the released trial's *result* does, which the
 * result-side {@see EndpointHealth} calls pin here end to end. The tick's candidate set is DEGRADED
 * only: a SUSPENDED webhook keeps its held backlog and state untouched (its new events are shed by
 * the dispatch gate — no row, no write).
 *
 * Deterministic clock: cooldowns and row ages are nudged via SQL, never slept on.
 *
 * @internal
 */
class WebhookHealthTickTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * Mirrors `shopware.webhook.health.cooldown_schedule_seconds[1]` — the tier a first failed
     * trial arms.
     */
    private const COOLDOWN_TIER_1 = 600;

    private Connection $connection;

    private EndpointLifecycle $lifecycle;

    private EndpointHealth $health;

    private EventDispatcherInterface $eventDispatcher;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->lifecycle = static::getContainer()->get(EndpointLifecycle::class);
        $this->health = static::getContainer()->get(EndpointHealth::class);
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');
    }

    public function testTickReleasesTheOldestHeldRowWhenTheCooldownHasElapsed(): void
    {
        $this->createWebhook('wh-due');
        $this->insertHealth('wh-due', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        // Distinct rows inserted in ascending order → ascending auto-increment ids; oldest = lowest id.
        $this->createDelivery('evt-1', 'wh-due', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-2', 'wh-due', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-3', 'wh-due', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->createWebhook('wh-cooling');
        $this->insertHealth('wh-cooling', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('+1 hour'), transientFailures: 5);
        $this->createDelivery('evt-cooling', 'wh-cooling', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->lifecycle->tick();

        // The oldest held row is the trial: claimable on both mirrored tables, due now.
        $this->assertDeliveryStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertNextRetryAtIsNow('evt-1');

        // One trial at a time — the younger rows stay held.
        $this->assertDeliveryStatus('evt-2', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertDeliveryStatus('evt-3', WebhookEventLogDefinition::STATUS_PAUSED);

        // Releasing itself never advances the ladder — only the trial's result does.
        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-due'));
        static::assertSame(0, $this->fetchDegradedCycleCount('wh-due'));

        // A still-running cooldown is not a candidate.
        $this->assertDeliveryStatus('evt-cooling', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-cooling'));
    }

    public function testTickNoOpsWhileAReleaseIsInFlight(): void
    {
        // A prior trial still claimable (not yet picked up by a worker)…
        $this->createWebhook('wh-claimable');
        $this->insertHealth('wh-claimable', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-trial-claimable', 'wh-claimable', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->createDelivery('evt-held-claimable', 'wh-claimable', WebhookEventLogDefinition::STATUS_PAUSED);

        // …and one mid-HTTP: both count as in flight, because the ladder advances on the trial's
        // result, not the wall clock — worker lag must not produce a second release.
        $this->createWebhook('wh-running');
        $this->insertHealth('wh-running', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-trial-running', 'wh-running', WebhookEventLogDefinition::STATUS_RUNNING);
        $this->createDelivery('evt-held-running', 'wh-running', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->lifecycle->tick();

        $this->assertDeliveryStatus('evt-held-claimable', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertDeliveryStatus('evt-held-running', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-claimable'));
        static::assertSame(EndpointState::Degraded->value, $this->fetchEndpointState('wh-running'));
    }

    public function testTwoTicksInQuickSuccessionReleaseExactlyOneTrial(): void
    {
        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-1', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-2', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->lifecycle->tick();
        $this->lifecycle->tick();

        // The second tick sees the first release in flight and no-ops — never a double release.
        static::assertSame(1, $this->countDeliveriesByStatus('wh-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY));
        static::assertSame(1, $this->countDeliveriesByStatus('wh-1', WebhookEventLogDefinition::STATUS_PAUSED));
    }

    public function testTickIdlePromotesAnIdleDegradedWebhookKeepingTheFailureStreaks(): void
    {
        $suspendedSince = new \DateTimeImmutable('2026-06-01 12:00:00.000');
        $this->createWebhook('wh-1', active: false, errorCount: 7);
        // DEGRADED after a suspension episode (suspended_since survives SUSPENDED → DEGRADED),
        // cooldown elapsed, nothing held, nothing in flight — a true idle endpoint.
        $this->insertHealth(
            'wh-1',
            EndpointState::Degraded,
            cooldownUntil: new \DateTimeImmutable('-1 hour'),
            transientFailures: 7,
            nonTransientFailures: 1,
            degradedCycleCount: 3,
            suspendedSince: $suspendedSince,
        );

        $events = $this->captureActivatedEvents();

        $this->lifecycle->tick();

        static::assertSame(EndpointState::Healthy->value, $this->fetchEndpointState('wh-1'));
        // Nothing was delivered, so nothing proved health: the streaks survive the promotion —
        // the first transient failure after traffic resumes re-degrades immediately.
        static::assertSame(7, $this->fetchTransientFailures('wh-1'));
        static::assertSame(1, $this->fetchNonTransientFailures('wh-1'));
        static::assertSame(0, $this->fetchDegradedCycleCount('wh-1'));
        static::assertNull($this->fetchHealthTimestamp('wh-1', 'cooldown_until'));
        static::assertNull($this->fetchHealthTimestamp('wh-1', 'suspended_since'), 'reaching HEALTHY ends the suspension episode');

        // BC mirror: HEALTHY reads active = 1 / error_count = 0 while the kept streaks stay internal.
        static::assertTrue($this->fetchActive('wh-1'));
        static::assertSame(0, $this->fetchErrorCount('wh-1'));

        $activated = $events();
        static::assertCount(1, $activated, 'idle promotion must dispatch exactly one WebhookActivatedEvent');
        static::assertSame($this->ids->get('wh-1'), $activated[0]->webhookId);
        static::assertSame(EndpointState::Degraded, $activated[0]->fromState);
        static::assertSame(WebhookActivationTrigger::Idle, $activated[0]->trigger);
        static::assertSame(
            $suspendedSince->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $activated[0]->clearedSuspendedSince?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'the event must carry the suspended_since value this recovery cleared',
        );
    }

    public function testTickNeverIdlePromotesWhileTheReleasedTrialIsPending(): void
    {
        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-1', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->lifecycle->tick();
        $this->assertDeliveryStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        // Nothing held anymore, but the released trial is unresolved — promotion must wait for it.
        $this->lifecycle->tick();

        static::assertSame(
            EndpointState::Degraded->value,
            $this->fetchEndpointState('wh-1'),
            'idle promotion must not race the pending trial — its result decides',
        );
    }

    public function testAReleasedTrialsTransientFailureAdvancesTheLadderAtTheResult(): void
    {
        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-1', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);
        $cooldownAtRelease = $this->fetchHealthTimestamp('wh-1', 'cooldown_until');

        $this->lifecycle->tick();

        // The release moved nothing on the health row.
        static::assertSame(0, $this->fetchDegradedCycleCount('wh-1'));
        static::assertSame($cooldownAtRelease, $this->fetchHealthTimestamp('wh-1', 'cooldown_until'));

        // The trial's transient result — landing with the cooldown elapsed — climbs one tier and
        // arms the next cooldown.
        $result = $this->health->recordFailure($this->ids->get('wh-1'), ErrorClassification::TransientServer, 1);

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

        $this->lifecycle->tick();
        $this->assertDeliveryStatus('evt-trial', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        // The trial's 2xx: DEGRADED → HEALTHY, and — unlike idle promotion — a real delivery proved
        // health, so the streaks reset.
        $this->health->recordSuccess($this->ids->get('wh-1'));

        static::assertSame(EndpointState::Healthy->value, $this->fetchEndpointState('wh-1'));
        static::assertSame(0, $this->fetchTransientFailures('wh-1'));
        static::assertNull($this->fetchHealthTimestamp('wh-1', 'cooldown_until'));

        // The held backlog resumes with the recovery: claimable on both mirrored tables, due now.
        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-held', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertNextRetryAtIsNow('evt-held');

        static::assertTrue($this->fetchActive('wh-1'));
        static::assertSame(0, $this->fetchErrorCount('wh-1'));
    }

    public function testTickCancelsAHeldRowAgedPastTheGraceWindowAndReleasesTheNextOldest(): void
    {
        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Degraded, cooldownUntil: new \DateTimeImmutable('-1 hour'), transientFailures: 5);
        $this->createDelivery('evt-old', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-fresh', 'wh-1', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->ageDeliveryRow('evt-old', '-25 hours');
        $this->ageDeliveryRow('evt-fresh', '-23 hours');

        $this->lifecycle->tick();

        // A release is a redelivery point: the over-age oldest row is cancelled, never redelivered —
        // delivery row gone, event log terminal with the cancel reason (payload retained for replay).
        $this->assertDeliveryDeleted('evt-old');
        $this->assertEventLogStatus('evt-old', WebhookEventLogDefinition::STATUS_FAILED);
        $this->assertFailureReason('evt-old', WebhookOutboxStore::CANCEL_REASON_SUSPENDED);

        // The next-oldest row inside the grace window becomes the trial instead.
        $this->assertDeliveryStatus('evt-fresh', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-fresh', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
    }

    public function testTickLeavesSuspendedWebhooksUntouched(): void
    {
        $suspendedSince = new \DateTimeImmutable('-1 day');
        $this->createWebhook('wh-held', active: false, errorCount: 3);
        $this->insertHealth('wh-held', EndpointState::Suspended, cooldownUntil: new \DateTimeImmutable('-1 hour'), nonTransientFailures: 3, suspendedSince: $suspendedSince);
        $this->createDelivery('evt-held', 'wh-held', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->createWebhook('wh-idle', active: false, errorCount: 3);
        $this->insertHealth('wh-idle', EndpointState::Suspended, cooldownUntil: new \DateTimeImmutable('-1 hour'), nonTransientFailures: 3, suspendedSince: $suspendedSince);

        $this->lifecycle->tick();

        // SUSPENDED is not in the tick's candidate set: no trial release despite the elapsed
        // cooldown, and never an idle promotion — the backlog stays held for a later recovery.
        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertSame(EndpointState::Suspended->value, $this->fetchEndpointState('wh-held'));
        static::assertSame(EndpointState::Suspended->value, $this->fetchEndpointState('wh-idle'));
        static::assertNotNull($this->fetchHealthTimestamp('wh-idle', 'suspended_since'));
    }

    /**
     * Subscribes a collector to WebhookActivatedEvent and returns a callable that detaches the
     * listener and yields the events captured so far. The dispatcher is the same `event_dispatcher`
     * WebhookHealthService dispatches through, so this observes the real promotion side effect.
     *
     * @return \Closure(): list<WebhookActivatedEvent>
     */
    private function captureActivatedEvents(): \Closure
    {
        /** @var \ArrayObject<int, WebhookActivatedEvent> $captured */
        $captured = new \ArrayObject();
        $listener = static function (WebhookActivatedEvent $event) use ($captured): void {
            $captured->append($event);
        };
        $this->eventDispatcher->addListener(WebhookActivatedEvent::class, $listener);

        return function () use ($captured, $listener): array {
            $this->eventDispatcher->removeListener(WebhookActivatedEvent::class, $listener);

            return array_values($captured->getArrayCopy());
        };
    }

    private function createWebhook(string $webhookKey, bool $active = true, int $errorCount = 0): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($webhookKey),
            'name' => $webhookKey,
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
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

    /**
     * Seeds a webhook_event_log row and its mirrored webhook_delivery row (the UNIQUE 1:1 pair the
     * outbox store works on). Inserting in call order yields ascending webhook_delivery.id
     * (auto-increment), so callers can reason about "oldest = lowest id".
     */
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

    /**
     * Deterministic clock control: backdates the delivery row's created_at (the grace-age input)
     * instead of sleeping.
     */
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

    private function assertFailureReason(string $eventKey, string $expectedReason): void
    {
        $reason = $this->connection->fetchOne(
            'SELECT failure_reason FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertSame($expectedReason, $reason);
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

    private function assertDeliveryDeleted(string $eventKey): void
    {
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertFalse($exists, 'Expected delivery row to be deleted');
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

    private function countDeliveriesByStatus(string $webhookKey, string $status): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_id = :id AND delivery_status = :status',
            ['id' => $this->ids->getBytes($webhookKey), 'status' => $status]
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

    private function fetchNonTransientFailures(string $webhookKey): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT consecutive_non_transient_failures FROM webhook_health WHERE webhook_id = :id',
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

    /**
     * For the nullable timestamp columns (cooldown_until, suspended_since).
     */
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
