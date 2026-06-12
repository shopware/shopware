<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Subscriber\ReactivateWebhookOnActivationSubscriber;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Locks down the admin reactivate gesture: a DAL `PATCH /api/webhook/{id}` with `active = true`
 * must be routed through {@see WebhookHealthService::reactivate} (trigger Manual) by
 * {@see ReactivateWebhookOnActivationSubscriber}, but only under `WEBHOOKS_REWORK`. Without this,
 * flipping `active = 1` over stale health would leave the webhook undeliverable — the gate reads
 * `webhook_health`, not the legacy column. Per the ADR's echo-guarded write table, only a write
 * that flips the mirrored value carries intent: the reset runs from SUSPENDED/DISABLED (health row
 * to HEALTHY, counters cleared, mirror repaired, held backlog resumed, exactly one
 * {@see WebhookActivatedEvent}); on DEGRADED the write is an echo and must not reset the breaker;
 * an already-HEALTHY write emits no event but still heals the mirror and stranded `paused` rows
 * (review M2). Updates go through `webhook.repository` so the real `webhook.written` event fires
 * the registered subscriber end to end.
 *
 * @internal
 */
class ReactivateWebhookOnActivationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SUSPENDED_SINCE = '2026-06-01 12:00:00.000';

    private Connection $connection;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $webhookRepository;

    private EventDispatcherInterface $eventDispatcher;

    private WebhookOutboxStore $outboxStore;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->webhookRepository = static::getContainer()->get('webhook.repository');
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');
        $this->outboxStore = static::getContainer()->get(WebhookOutboxStore::class);
    }

    /**
     * Catches: an `active = true` update not reaching `reactivate()` with the Manual trigger under
     * flag-on, or a reactivation that flips the state but strands the held backlog or the episode
     * markers.
     */
    public function testActivatingSuspendedWebhookHealsItUnderFlagOn(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 4);
        $this->seedHealth('wh', EndpointState::Suspended, [
            'consecutive_transient_failures' => 2,
            'consecutive_non_transient_failures' => 3,
            'degraded_cycle_count' => 2,
            'cooldown_until' => (new \DateTimeImmutable('+4 hours'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'suspended_since' => self::SUSPENDED_SINCE,
        ]);
        $this->seedHeldRow('evt-held', 'wh');

        $events = $this->captureActivatedEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => true]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Healthy->value, $health['endpoint_state'], 'Activating a SUSPENDED webhook under flag-on must reset its health row to HEALTHY.');
        static::assertSame(0, (int) $health['consecutive_transient_failures']);
        static::assertSame(0, (int) $health['consecutive_non_transient_failures']);
        static::assertSame(0, (int) $health['degraded_cycle_count']);
        static::assertNull($health['cooldown_until']);
        static::assertNull($health['suspended_since'], 'reaching HEALTHY ends the suspension episode');

        $webhook = $this->fetchBcColumns('wh');
        static::assertSame(1, $webhook['active'], 'BC mirror must set webhook.active = 1 for the now-HEALTHY endpoint.');
        static::assertSame(0, $webhook['error_count'], 'BC mirror must zero error_count for a HEALTHY endpoint.');

        $this->assertHeldRowResumed('evt-held');

        $activated = $events();
        static::assertCount(1, $activated, 'reactivate() must dispatch exactly one WebhookActivatedEvent for the SUSPENDED → HEALTHY transition.');
        static::assertSame($this->ids->get('wh'), $activated[0]->webhookId);
        static::assertSame(EndpointState::Suspended, $activated[0]->fromState);
        static::assertSame(WebhookActivationTrigger::Manual, $activated[0]->trigger);
        static::assertSame(
            self::SUSPENDED_SINCE,
            $activated[0]->clearedSuspendedSince?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'the event must carry the suspended_since value this recovery cleared',
        );
    }

    /**
     * Catches: the operator-disabled recovery being blocked by its own provenance. Automation never
     * undoes an operator kill — admin `PATCH active = true` is exactly the gesture that reverses
     * it, so it must reset the webhook regardless of `disabled_origin`.
     */
    public function testActivatingOperatorDisabledWebhookRecoversIt(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 2);
        $this->seedHealth('wh', EndpointState::Disabled, [
            'consecutive_transient_failures' => 2,
            'disabled_since' => '2026-06-02 12:00:00.000',
            'disabled_origin' => DisabledOrigin::Operator->value,
        ]);

        $events = $this->captureActivatedEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => true]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Healthy->value, $health['endpoint_state'], 'PATCH active=true is the operator-disabled recovery — it must reset DISABLED regardless of origin.');
        static::assertSame(0, (int) $health['consecutive_transient_failures']);
        static::assertNull($health['disabled_since'], 'reaching HEALTHY ends the disable episode');
        static::assertNull($health['disabled_origin'], 'the operator provenance is cleared with the episode');

        $webhook = $this->fetchBcColumns('wh');
        static::assertSame(1, $webhook['active']);
        static::assertSame(0, $webhook['error_count']);

        $activated = $events();
        static::assertCount(1, $activated, 'the recovery must dispatch exactly one WebhookActivatedEvent');
        static::assertSame(EndpointState::Disabled, $activated[0]->fromState);
        static::assertSame(WebhookActivationTrigger::Manual, $activated[0]->trigger);
        static::assertNull($activated[0]->clearedSuspendedSince, 'no suspension episode was cleared — the webhook was operator-disabled, not suspended');
    }

    /**
     * Catches: a missing echo guard on DEGRADED. DEGRADED already mirrors `active = 1`, so writing
     * `active = true` again carries no intent — resetting would wipe the ladder and the failure
     * streak mid-episode. Everything must stay as it was, and no event may fire.
     */
    public function testActivatingDegradedWebhookIsAnEchoAndDoesNotResetTheBreaker(): void
    {
        $cooldown = (new \DateTimeImmutable('+4 hours'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->seedWebhook('wh', active: true, errorCount: 4);
        $this->seedHealth('wh', EndpointState::Degraded, [
            'consecutive_transient_failures' => 4,
            'degraded_cycle_count' => 1,
            'cooldown_until' => $cooldown,
        ]);
        $this->seedHeldRow('held-event', 'wh');

        $events = $this->captureActivatedEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => true]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Degraded->value, $health['endpoint_state'], 'an echo write must not reset a DEGRADED webhook');
        static::assertSame(4, (int) $health['consecutive_transient_failures'], 'the failure streak must survive the echo');
        static::assertSame(1, (int) $health['degraded_cycle_count'], 'the ladder must survive the echo');
        static::assertSame($cooldown, $health['cooldown_until'], 'the running cooldown must survive the echo');
        static::assertSame(
            'paused',
            $this->fetchDeliveryStatus('held-event'),
            'a refused echo must not resume the deliberately held backlog onto a still-breaking endpoint',
        );
        static::assertCount(0, $events(), 'an echo must not dispatch a WebhookActivatedEvent');
    }

    /**
     * Catches: a non-idempotent heal. An already-HEALTHY write dispatches no event, but it must
     * still repair a drifted legacy mirror (a flag-off auto-disable left `active = 0` over a
     * healthy row) and resume `paused` rows stranded by a crash mid-recovery (review M2).
     */
    public function testActivatingAlreadyHealthyWebhookRepairsMirrorDriftAndStrandedHolds(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 3);
        $this->seedHealth('wh', EndpointState::Healthy);
        $this->seedHeldRow('evt-stranded', 'wh');

        $events = $this->captureActivatedEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => true]],
                Context::createDefaultContext(),
            );
        });

        static::assertCount(0, $events(), 'No transition happened — no WebhookActivatedEvent may be dispatched.');
        static::assertSame(EndpointState::Healthy->value, (string) $this->fetchHealthRow('wh')['endpoint_state']);

        $webhook = $this->fetchBcColumns('wh');
        static::assertSame(1, $webhook['active']);
        static::assertSame(0, $webhook['error_count'], 'the idempotent heal must repair the drifted legacy error_count even without a transition');

        $this->assertHeldRowResumed('evt-stranded');
    }

    /**
     * Catches: a missing `Feature::isActive` guard. Flag-off, the legacy raw `active = 1` write
     * stands alone: the SUSPENDED health row and the held backlog stay untouched, no event fires.
     */
    public function testActivationIsNoOpUnderFlagOff(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 4);
        $this->seedHealth('wh', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'suspended_since' => self::SUSPENDED_SINCE,
        ]);
        $this->seedHeldRow('evt-held', 'wh');

        $events = $this->captureActivatedEvents();

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => true]],
                Context::createDefaultContext(),
            );
        });

        static::assertCount(0, $events(), 'Flag-off, the subscriber must not run — no WebhookActivatedEvent may be dispatched.');
        static::assertSame(
            EndpointState::Suspended->value,
            (string) $this->fetchHealthRow('wh')['endpoint_state'],
            'Flag-off, the legacy raw active write stands alone — the health row must stay SUSPENDED.',
        );
        static::assertSame(
            WebhookEventLogDefinition::STATUS_PAUSED,
            $this->fetchDeliveryStatus('evt-held'),
            'Flag-off, the held backlog must stay paused.',
        );
    }

    /**
     * Catches: a deactivation (`active = false`) being treated as a reactivation — only
     * `active === true` may trigger `reactivate()`.
     */
    public function testDeactivationDoesNotReactivate(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 4);
        $this->seedHealth('wh', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'suspended_since' => self::SUSPENDED_SINCE,
        ]);

        $events = $this->captureActivatedEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        static::assertCount(0, $events(), 'A deactivation (active = false) must not trigger reactivate — no WebhookActivatedEvent may be dispatched.');
        static::assertSame(
            EndpointState::Suspended->value,
            (string) $this->fetchHealthRow('wh')['endpoint_state'],
            'Deactivating a SUSPENDED webhook must leave its health row SUSPENDED.',
        );
    }

    /**
     * Collects WebhookActivatedEvent dispatches; the returned closure detaches the listener and
     * returns what was captured. Same dispatcher the service uses, so this sees the real side effect.
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

    private function seedWebhook(string $key, bool $active, int $errorCount): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://example.com/' . $key,
            'active' => (int) $active,
            'error_count' => $errorCount,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function seedHealth(string $key, EndpointState $state, array $extra = []): void
    {
        $this->connection->insert('webhook_health', array_merge([
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], $extra));
    }

    /**
     * Seeds a held (`paused`) outbox row through the store's own Hold writer — the same row a
     * DEGRADED/SUSPENDED hold leaves behind for reactivate to resume.
     */
    private function seedHeldRow(string $eventKey, string $webhookKey): void
    {
        $entry = $this->outboxStore->recordHeldOutboxEntry(new OutboxInsert(
            webhookEventId: $this->ids->get($eventKey),
            webhookId: $this->ids->get($webhookKey),
            partitionKey: Hasher::hashBinary('default', 'xxh128'),
            serializedMessage: 'serialized-payload',
        ));
        static::assertNotNull($entry);
    }

    private function assertHeldRowResumed(string $eventKey): void
    {
        static::assertSame(
            WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            $this->fetchDeliveryStatus($eventKey),
            'reactivate must resume the held backlog — the paused delivery row becomes claimable',
        );
        static::assertSame(
            WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            (string) $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => $this->ids->getBytes($eventKey)],
            ),
            'the resume must mirror the flip onto webhook_event_log',
        );
    }

    private function fetchDeliveryStatus(string $eventKey): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchHealthRow(string $key): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT endpoint_state, consecutive_transient_failures, consecutive_non_transient_failures,
                    degraded_cycle_count, cooldown_until, suspended_since, disabled_since, disabled_origin
             FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)],
        );
        static::assertIsArray($row);

        return $row;
    }

    /**
     * @return array{active: int, error_count: int}
     */
    private function fetchBcColumns(string $key): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)],
        );
        static::assertIsArray($row);

        return ['active' => (int) $row['active'], 'error_count' => (int) $row['error_count']];
    }
}
