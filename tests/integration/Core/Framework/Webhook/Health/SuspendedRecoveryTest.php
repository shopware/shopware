<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\WebhookDispatchDecision;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Locks down how a SUSPENDED webhook recovers (ADR §Half-open recovery, §SUSPENDED). A suspended
 * webhook keeps its paused backlog and sheds new events; recovery happens one trial at a time —
 * either the task tick releases the oldest held row once the cooldown elapses, or (with nothing
 * held and nothing in flight) the gate admits one natural event. A trial 2xx climbs exactly one
 * state, a transient failure re-holds the row one cooldown tier up, a non-transient failure fails
 * the row; only the 7-day wall clock gives up entirely. This matters because a wrong trial flow
 * either hammers a broken endpoint or never lets a fixed one back in. Uses the real
 * {@see WebhookHealthService}; cooldowns and row ages are rewritten via SQL, never slept on.
 *
 * @internal
 */
class SuspendedRecoveryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const URL = 'https://endpoint.example.com/hook';

    /**
     * Mirrors `shopware.webhook.health.cooldown_schedule_seconds` ([300, 600, …, 14400]).
     */
    private const COOLDOWN_TIER_0 = 300;

    private const COOLDOWN_TIER_1 = 600;

    private const COOLDOWN_TOP = 14400;

    private const TOP_TIER_INDEX = 5;

    private IdsCollection $ids;

    private Connection $connection;

    private WebhookHealthService $service;

    private WebhookOutboxStore $outboxStore;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->service = static::getContainer()->get(WebhookHealthService::class);
        $this->outboxStore = static::getContainer()->get(WebhookOutboxStore::class);
    }

    public function testTickReleasesTheOldestHeldRowWhileTheGateKeepsShedding(): void
    {
        $this->seedWebhook('wh', active: false);
        $this->seedHealth('wh', EndpointState::Suspended, cnf: 3, suspendedSince: '-2 days', cooldownUntil: '-1 minute');
        $this->createDelivery('evt-oldest', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-younger', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);

        // Held rows come first: while one exists the gate sheds new events — no admission, no write.
        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertLessThan(0, $this->cooldownDeltaSeconds('wh'), 'a shed event must not re-arm the elapsed cooldown');

        // The task releases the oldest held row as the one trial.
        $this->service->tick();

        $this->assertDeliveryStatus('evt-oldest', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-oldest', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertDeliveryStatus('evt-younger', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame(0, $this->fetchCycle('wh'), 'releasing never advances the ladder — the trial result does');

        // With the trial in flight and a row still held, new events keep shedding.
        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
    }

    public function testGateAdmitsExactlyOneNaturalTrialPerBurst(): void
    {
        $this->seedWebhook('wh', active: false);
        $this->seedHealth('wh', EndpointState::Suspended, cnf: 3, suspendedSince: '-2 days', cooldownUntil: '-1 minute');

        // Nothing held, nothing in flight, cooldown elapsed: the first natural event is the trial.
        static::assertSame(WebhookDispatchDecision::Deliver, $this->service->gateFor($this->ids->get('wh')));

        // Admission advances the ladder and re-arms the cooldown up front, so the trial's own
        // failure result cannot count a second time…
        static::assertSame(1, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_1);

        // …and the rest of the burst sees the running cooldown: exactly one trial.
        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(1, $this->fetchCycle('wh'));
        $this->assertState('wh', EndpointState::Suspended);
    }

    public function testAuthStreakTripEntersTheLadderAtTierZero(): void
    {
        $this->seedWebhook('wh');
        $this->seedHealth('wh', EndpointState::Healthy);

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);
        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);
        // Two auth failures could be WAF/gateway noise — not enough to suspend.
        $this->assertState('wh', EndpointState::Healthy);

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);

        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame(0, $this->fetchCycle('wh'), 'a direct trip enters at tier 0 — first trial after five minutes');
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_0);
        static::assertNotNull($this->fetchColumn('wh', 'suspended_since'));
        static::assertFalse($this->fetchActive('wh'), 'SUSPENDED mirrors active = 0');
    }

    public function testGoneTripEntersTheLadderAtTierZero(): void
    {
        $this->seedWebhook('wh');
        $this->seedHealth('wh', EndpointState::Healthy);

        // 410 means the endpoint says it is gone — it suspends without a streak.
        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientEndpoint, 1);

        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame(0, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_0);
    }

    public function testExhaustedDegradedCyclesArriveAtTheTopTier(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: 5, cycle: self::TOP_TIER_INDEX, cooldownUntil: '-1 minute');

        // The last scheduled trial fails: DEGRADED gets one trial per schedule entry, no more.
        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Suspended, $result);
        static::assertSame(self::TOP_TIER_INDEX, $this->fetchCycle('wh'), 'an endpoint that burned ~6 h of trials gets no fast retries');
        $this->assertCooldownAbout('wh', self::COOLDOWN_TOP);
        static::assertNotNull($this->fetchColumn('wh', 'suspended_since'));
    }

    public function testTrialSuccessClimbsToDegradedThenHealthyResumingHeldRows(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Suspended, ctf: 5, cycle: self::TOP_TIER_INDEX, suspendedSince: '-3 days', cooldownUntil: '-1 minute');
        // Insert order = id order: the stale row is the oldest, the backlog row the youngest.
        $this->createDelivery('evt-stale', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-trial', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-second-trial', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-backlog', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->ageDeliveryRow('evt-stale', '-25 hours');
        $suspendedSinceBefore = $this->fetchColumn('wh', 'suspended_since');

        // The over-age head row is cancelled (still replayable); the next-oldest row inside the
        // grace window becomes the trial.
        $this->service->tick();

        $this->assertDeliveryDeleted('evt-stale');
        $this->assertEventLogStatus('evt-stale', WebhookEventLogDefinition::STATUS_FAILED);
        $this->assertFailureReason('evt-stale', WebhookOutboxStore::CANCEL_REASON_SUSPENDED);
        $this->assertDeliveryStatus('evt-trial', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        // The trial 2xx climbs exactly one state: DEGRADED at tier 0, streaks reset, suspended_since kept.
        $this->deliverSuccessfully('evt-trial');
        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Degraded);
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertSame(0, $this->fetchCtf('wh'));
        static::assertSame(0, $this->fetchCnf('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_0);
        static::assertSame($suspendedSinceBefore, $this->fetchColumn('wh', 'suspended_since'), 'suspended_since is preserved until HEALTHY');
        $this->assertDeliveryStatus('evt-second-trial', WebhookEventLogDefinition::STATUS_PAUSED);

        // The next cooldown elapses: the next-oldest held row becomes the DEGRADED trial; its 2xx
        // earns HEALTHY and resumes the held backlog on both tables, due now.
        $this->nudgeCooldown('wh', '-1 minute');
        $this->service->tick();
        $this->assertDeliveryStatus('evt-second-trial', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        $this->deliverSuccessfully('evt-second-trial');
        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Healthy);
        static::assertNull($this->fetchColumn('wh', 'cooldown_until'));
        static::assertNull($this->fetchColumn('wh', 'suspended_since'), 'reaching HEALTHY ends the suspension episode');
        $this->assertDeliveryStatus('evt-backlog', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-backlog', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertNextRetryAtIsNow('evt-backlog');
        static::assertTrue($this->fetchActive('wh'));
        static::assertSame(0, $this->fetchErrorCount('wh'));
    }

    public function testTransientTrialFailureReHoldsTheRowOneTierUp(): void
    {
        $this->seedWebhook('wh', active: false);
        $this->seedHealth('wh', EndpointState::Suspended, ctf: 5, cnf: 3, suspendedSince: '-2 days', cooldownUntil: '-1 minute');
        $this->createDelivery('evt-1', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $suspendedSinceBefore = $this->fetchColumn('wh', 'suspended_since');

        $this->service->tick();
        $this->assertDeliveryStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $entry = $this->startDelivery('evt-1');

        // The result lands with the cooldown elapsed, so it counts: one tier up, next cooldown armed.
        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Suspended, $result, 'the returned state drives the result-side re-hold');
        static::assertSame(1, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_1);
        static::assertSame(3, $this->fetchCnf('wh'), 'a transient trial failure never feeds the auth streak');
        static::assertSame($suspendedSinceBefore, $this->fetchColumn('wh', 'suspended_since'), 'a failed trial never resets the 7-day clock');

        // The returned SUSPENDED state tells the caller to re-hold the row…
        static::assertTrue($this->outboxStore->markPaused($entry, null));
        $this->assertDeliveryStatus('evt-1', WebhookEventLogDefinition::STATUS_PAUSED);

        // …and once the next cooldown elapses, the re-held row becomes the trial again.
        $this->nudgeCooldown('wh', '-1 minute');
        $this->service->tick();
        $this->assertDeliveryStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
    }

    public function testAuthTrialFailureFailsTheRowAndAdvancesTheStreak(): void
    {
        $this->seedWebhook('wh', active: false);
        $this->seedHealth('wh', EndpointState::Suspended, cnf: 3, suspendedSince: '-2 days', cooldownUntil: '-1 minute');
        $this->createDelivery('evt-1', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->tick();
        $entry = $this->startDelivery('evt-1');

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);

        static::assertSame(EndpointState::Suspended, $result);
        static::assertSame(4, $this->fetchCnf('wh'), 'an auth trial failure keeps counting the streak');
        static::assertSame(1, $this->fetchCycle('wh'), 'a task-released trial counts the ladder at its result — the cooldown was elapsed');

        // A non-transient failure is terminal for the row: FAILED, never re-held.
        static::assertTrue($this->outboxStore->markFailed($entry, null));
        $this->assertDeliveryDeleted('evt-1');
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_FAILED);
        static::assertSame(0, $this->countDeliveriesByStatus('wh', WebhookEventLogDefinition::STATUS_PAUSED));
    }

    public function testAuthBrokenEndpointNeverRecoversOnTrafficAndRetiresAtTheBound(): void
    {
        $this->seedWebhook('wh', active: false);
        $this->seedHealth('wh', EndpointState::Suspended, cnf: 3, suspendedSince: '-8 days', cooldownUntil: '-1 minute');
        $suspendedSinceBefore = $this->fetchColumn('wh', 'suspended_since');

        // The gate admits a natural trial (nothing held) and re-arms the cooldown at admission…
        static::assertSame(WebhookDispatchDecision::Deliver, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(1, $this->fetchCycle('wh'));

        // …so its 401 result counts the streak but cannot move the ladder twice, and the 7-day
        // clock keeps running — a failed trial never resets suspended_since.
        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);

        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame(4, $this->fetchCnf('wh'));
        static::assertSame(1, $this->fetchCycle('wh'), 'no extra ladder count while the admission lease holds');
        static::assertSame($suspendedSinceBefore, $this->fetchColumn('wh', 'suspended_since'));

        // Past the 7-day bound the tick retires it; DISABLED never recovers on traffic.
        $this->service->tick();

        $this->assertState('wh', EndpointState::Disabled);
        static::assertSame(DisabledOrigin::Escalation->value, $this->fetchColumn('wh', 'disabled_origin'));
        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
    }

    public function testRetirementIsANoOpOnAWebhookATrialRecoveredToDegraded(): void
    {
        // suspended_since is past the bound, but a trial 2xx already moved it to DEGRADED — the
        // retirement sweep only matches SUSPENDED rows, so the recovered webhook is untouched.
        $this->seedWebhook('wh', active: true, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: 5, suspendedSince: '-8 days', cooldownUntil: '+1 hour');

        $this->service->tick();

        $this->assertState('wh', EndpointState::Degraded);
        static::assertNull($this->fetchColumn('wh', 'disabled_since'));
    }

    private function seedWebhook(string $key, bool $active = true, int $errorCount = 0): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => self::URL,
            'active' => (int) $active,
            'error_count' => $errorCount,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function seedHealth(
        string $key,
        EndpointState $state,
        int $ctf = 0,
        int $cnf = 0,
        int $cycle = 0,
        ?string $suspendedSince = null,
        ?string $cooldownUntil = null,
    ): void {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'consecutive_transient_failures' => $ctf,
            'consecutive_non_transient_failures' => $cnf,
            'degraded_cycle_count' => $cycle,
            'cooldown_until' => $cooldownUntil !== null ? (new \DateTimeImmutable($cooldownUntil))->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'suspended_since' => $suspendedSince !== null ? (new \DateTimeImmutable($suspendedSince))->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * Seeds a webhook_event_log row and its mirrored webhook_delivery row. Rows get ascending
     * auto-increment ids in call order, so "oldest = lowest id".
     */
    private function createDelivery(string $eventKey, string $webhookKey, string $deliveryStatus): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => $deliveryStatus,
            'webhook_name' => $webhookKey,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => self::URL,
            'created_at' => $now,
        ]);

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => $this->ids->getBytes($webhookKey),
            'partition_key' => Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128'),
            'delivery_status' => $deliveryStatus,
            'execution_count' => 0,
            'created_at' => $now,
        ]);
    }

    /**
     * Backdates the delivery row's created_at (the grace-age input) instead of sleeping.
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

    /**
     * Rewrites cooldown_until instead of waiting it out.
     */
    private function nudgeCooldown(string $key, string $modifier): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_health SET cooldown_until = :cooldown WHERE webhook_id = :id',
            [
                'cooldown' => (new \DateTimeImmutable($modifier))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->getBytes($key),
            ]
        );
    }

    /**
     * Claims the released row exactly like the receiver's worker would.
     */
    private function startDelivery(string $eventKey): OutboxEntry
    {
        $entry = $this->outboxStore->markRunning($this->ids->get($eventKey));
        static::assertNotNull($entry, 'the released row must be claimable for the worker');

        return $entry;
    }

    private function deliverSuccessfully(string $eventKey): void
    {
        static::assertTrue($this->outboxStore->markSuccess($this->startDelivery($eventKey), null));
    }

    private function assertState(string $key, EndpointState $expected): void
    {
        static::assertSame($expected->value, $this->fetchColumn($key, 'endpoint_state'));
    }

    private function assertCooldownAbout(string $key, int $secondsFromNow): void
    {
        static::assertEqualsWithDelta(
            $secondsFromNow,
            $this->cooldownDeltaSeconds($key),
            60,
            \sprintf('cooldown_until must be about %d seconds from now', $secondsFromNow),
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

    private function assertDeliveryDeleted(string $eventKey): void
    {
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertFalse($exists, 'Expected delivery row to be deleted');
    }

    private function assertNextRetryAtIsNow(string $eventKey): void
    {
        $nextRetryAt = $this->connection->fetchOne(
            'SELECT next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertIsString($nextRetryAt, 'a resumed row is due now, so next_retry_at is set');
        static::assertEqualsWithDelta(
            (new \DateTimeImmutable())->getTimestamp(),
            (new \DateTimeImmutable($nextRetryAt))->getTimestamp(),
            5,
        );
    }

    private function countDeliveriesByStatus(string $webhookKey, string $status): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_id = :id AND delivery_status = :status',
            ['id' => $this->ids->getBytes($webhookKey), 'status' => $status]
        );
    }

    private function fetchColumn(string $key, string $column): ?string
    {
        $value = $this->connection->fetchOne(
            \sprintf('SELECT %s FROM webhook_health WHERE webhook_id = :id', $column),
            ['id' => $this->ids->getBytes($key)]
        );

        if ($value === false || $value === null) {
            return null;
        }

        return (string) $value;
    }

    private function fetchCtf(string $key): int
    {
        return (int) $this->fetchColumn($key, 'consecutive_transient_failures');
    }

    private function fetchCnf(string $key): int
    {
        return (int) $this->fetchColumn($key, 'consecutive_non_transient_failures');
    }

    private function fetchCycle(string $key): int
    {
        return (int) $this->fetchColumn($key, 'degraded_cycle_count');
    }

    private function cooldownDeltaSeconds(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(3), cooldown_until) FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchActive(string $key): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT active FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchErrorCount(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT error_count FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }
}
