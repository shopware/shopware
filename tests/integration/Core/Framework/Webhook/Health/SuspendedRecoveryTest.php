<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
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
 * @internal
 */
#[Package('framework')]
class SuspendedRecoveryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const URL = 'https://endpoint.example.com/hook';

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

        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertLessThan(0, $this->cooldownDeltaSeconds('wh'));

        $this->service->tick();

        $this->assertDeliveryStatus('evt-oldest', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-oldest', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertDeliveryStatus('evt-younger', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame(0, $this->fetchCycle('wh'));

        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
    }

    public function testGateAdmitsExactlyOneNaturalTrialPerBurst(): void
    {
        $this->seedWebhook('wh', active: false);
        $this->seedHealth('wh', EndpointState::Suspended, cnf: 3, suspendedSince: '-2 days', cooldownUntil: '-1 minute');

        static::assertSame(WebhookDispatchDecision::Deliver, $this->service->gateFor($this->ids->get('wh')));

        static::assertSame(1, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_1);

        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(1, $this->fetchCycle('wh'));
        $this->assertState('wh', EndpointState::Suspended);
    }

    public function testGateKeepsSheddingBurstStragglersAfterTheTrialSucceeds(): void
    {
        $this->seedWebhook('wh', active: false);
        $this->seedHealth('wh', EndpointState::Suspended, cnf: 3, suspendedSince: '-2 days', cooldownUntil: '-1 minute');

        static::assertSame(WebhookDispatchDecision::Deliver, $this->service->gateFor($this->ids->get('wh')));

        $this->service->recordSuccess($this->ids->get('wh'));
        $this->assertState('wh', EndpointState::Degraded);
        static::assertNotNull($this->fetchColumn('wh', 'suspended_since'));

        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(0, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_0);
    }

    public function testGateAdmitsTheNaturalTrialFromRecoveringDegradedWhenDue(): void
    {
        $this->seedWebhook('wh', active: true);
        $this->seedHealth('wh', EndpointState::Degraded, suspendedSince: '-1 day', cooldownUntil: '-1 minute');

        static::assertSame(WebhookDispatchDecision::Deliver, $this->service->gateFor($this->ids->get('wh')));

        static::assertSame(1, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_1);
        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
    }

    public function testGateHoldsOnDegradedOutsideASuspensionIncident(): void
    {
        $this->seedWebhook('wh', active: true);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: 5, cooldownUntil: '+5 minutes');

        static::assertSame(WebhookDispatchDecision::Hold, $this->service->gateFor($this->ids->get('wh')));
    }

    public function testAuthStreakTripEntersTheLadderAtTierZero(): void
    {
        $this->seedWebhook('wh');
        $this->seedHealth('wh', EndpointState::Healthy);

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);
        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);
        $this->assertState('wh', EndpointState::Healthy);

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);

        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame(0, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_0);
        static::assertNotNull($this->fetchColumn('wh', 'suspended_since'));
        static::assertFalse($this->fetchActive('wh'));
    }

    public function testGoneTripEntersTheLadderAtTierZero(): void
    {
        $this->seedWebhook('wh');
        $this->seedHealth('wh', EndpointState::Healthy);

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientEndpoint, 1);

        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame(0, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_0);
    }

    public function testExhaustedDegradedCyclesArriveAtTheTopTier(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: 5, cycle: self::TOP_TIER_INDEX, cooldownUntil: '-1 minute');

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Suspended, $result);
        static::assertSame(self::TOP_TIER_INDEX, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TOP);
        static::assertNotNull($this->fetchColumn('wh', 'suspended_since'));
    }

    public function testTrialSuccessClimbsToDegradedThenHealthyResumingHeldRows(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Suspended, ctf: 5, cycle: self::TOP_TIER_INDEX, suspendedSince: '-3 days', cooldownUntil: '-1 minute');
        // Auto-increment ids preserve insertion order for the oldest-row trial selection.
        $this->createDelivery('evt-stale', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-trial', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-second-trial', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->createDelivery('evt-backlog', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->ageDeliveryRow('evt-stale', '-25 hours');
        $suspendedSinceBefore = $this->fetchColumn('wh', 'suspended_since');

        $this->service->tick();

        $this->assertDeliveryDeleted('evt-stale');
        $this->assertEventLogStatus('evt-stale', WebhookEventLogDefinition::STATUS_FAILED);
        $this->assertFailureReason('evt-stale', WebhookOutboxStore::CANCEL_REASON_HELD_EXPIRED);
        $this->assertDeliveryStatus('evt-trial', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        $this->deliverSuccessfully('evt-trial');
        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Degraded);
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertSame(0, $this->fetchCtf('wh'));
        static::assertSame(0, $this->fetchCnf('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_0);
        static::assertSame($suspendedSinceBefore, $this->fetchColumn('wh', 'suspended_since'));
        $this->assertDeliveryStatus('evt-second-trial', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->nudgeCooldown('wh', '-1 minute');
        $this->service->tick();
        $this->assertDeliveryStatus('evt-second-trial', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        $this->deliverSuccessfully('evt-second-trial');
        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Healthy);
        static::assertNull($this->fetchColumn('wh', 'cooldown_until'));
        static::assertNull($this->fetchColumn('wh', 'suspended_since'));
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

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Suspended, $result);
        static::assertSame(1, $this->fetchCycle('wh'));
        $this->assertCooldownAbout('wh', self::COOLDOWN_TIER_1);
        static::assertSame(3, $this->fetchCnf('wh'));
        static::assertSame($suspendedSinceBefore, $this->fetchColumn('wh', 'suspended_since'));

        static::assertTrue($this->outboxStore->markPaused($entry, null));
        $this->assertDeliveryStatus('evt-1', WebhookEventLogDefinition::STATUS_PAUSED);

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
        static::assertSame(4, $this->fetchCnf('wh'));
        static::assertSame(1, $this->fetchCycle('wh'));

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

        static::assertSame(WebhookDispatchDecision::Deliver, $this->service->gateFor($this->ids->get('wh')));
        static::assertSame(1, $this->fetchCycle('wh'));

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);

        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame(4, $this->fetchCnf('wh'));
        static::assertSame(1, $this->fetchCycle('wh'));
        static::assertSame($suspendedSinceBefore, $this->fetchColumn('wh', 'suspended_since'));

        $this->service->tick();

        $this->assertState('wh', EndpointState::Disabled);
        static::assertSame(DisabledOrigin::Escalation->value, $this->fetchColumn('wh', 'disabled_origin'));
        static::assertSame(WebhookDispatchDecision::Skip, $this->service->gateFor($this->ids->get('wh')));
    }

    public function testRetirementIsANoOpOnAWebhookATrialRecoveredToDegraded(): void
    {
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
