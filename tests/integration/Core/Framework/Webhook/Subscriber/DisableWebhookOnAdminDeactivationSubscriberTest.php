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
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Subscriber\DisableWebhookOnAdminDeactivationSubscriber;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Locks down the admin deactivate gesture: a DAL `PATCH /api/webhook/{id}` with `active = false`
 * is routed through {@see WebhookHealthService::disableByOperator} by
 * {@see DisableWebhookOnAdminDeactivationSubscriber} under `WEBHOOKS_REWORK`. The echo guard is
 * the point: without it, the Admin UI saving a suspended webhook back unchanged (writing
 * `active = false` again) would count as a deliberate operator kill. Intent is read only from a
 * write that actually flips the mirrored value (the ADR's four-row write table):
 *
 *  - HEALTHY/DEGRADED (mirror `active = 1`) → a genuine kill: DISABLED with origin operator, the
 *    undelivered backlog dropped (`webhook_disabled`), one {@see WebhookDisabledEvent}.
 *  - SUSPENDED / already-DISABLED (mirror already `0`) → an echo: a complete no-op.
 *  - No health row → a DISABLED row is inserted, so a late delivery failure cannot insert a fresh
 *    HEALTHY row and resurrect `active = 1`.
 *
 * Updates go through `webhook.repository` so the real `webhook.written` event fires the registered
 * subscriber end to end.
 *
 * @internal
 */
class DisableWebhookOnAdminDeactivationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SUSPENDED_SINCE = '2026-06-01 12:00:00.000';

    private const DISABLED_SINCE = '2026-06-02 12:00:00.000';

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
     * Catches: the kill not landing on a HEALTHY webhook, or a kill that leaves the queued backlog
     * deliverable to the endpoint the operator just switched off.
     */
    public function testDeactivatingHealthyWebhookDisablesItWithOperatorOriginAndDropsTheBacklog(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy);
        $this->seedOutboxRow('evt-queued', 'wh', held: false);

        $events = $this->captureDisabledEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Disabled->value, $health['endpoint_state'], 'PATCH active=false on HEALTHY flips the mirrored value — a genuine operator kill.');
        static::assertSame(DisabledOrigin::Operator->value, $health['disabled_origin'], 'the kill must carry operator provenance');
        static::assertNotNull($health['disabled_since'], 'the → DISABLED transition must stamp disabled_since');
        static::assertSame(0, $this->fetchWebhookActive('wh'), 'the BC mirror must hold active = 0 for the killed webhook');

        $this->assertBacklogRowDropped('evt-queued');

        $disabled = $events();
        static::assertCount(1, $disabled, 'the kill must dispatch exactly one WebhookDisabledEvent');
        static::assertSame($this->ids->get('wh'), $disabled[0]->webhookId);
        static::assertSame(EndpointState::Healthy, $disabled[0]->fromState);
        static::assertSame(DisabledOrigin::Operator, $disabled[0]->origin);
    }

    /**
     * Catches: the kill sparing a DEGRADED webhook's held backlog. DISABLED drops everything still
     * undelivered, held rows included, and clears the cooldown so no releases keep running against
     * a killed endpoint.
     */
    public function testDeactivatingDegradedWebhookDisablesItCancellingTheHeldBacklog(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 4);
        $this->seedHealth('wh', EndpointState::Degraded, [
            'consecutive_transient_failures' => 4,
            'degraded_cycle_count' => 1,
            'cooldown_until' => (new \DateTimeImmutable('+4 hours'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->seedOutboxRow('evt-held', 'wh', held: true);

        $events = $this->captureDisabledEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Disabled->value, $health['endpoint_state'], 'PATCH active=false on DEGRADED (mirror active=1) is a genuine flip — the operator kill applies.');
        static::assertSame(DisabledOrigin::Operator->value, $health['disabled_origin']);
        static::assertNull($health['cooldown_until'], 'the kill must clear the cooldown — no further releases for a killed endpoint');

        $this->assertBacklogRowDropped('evt-held');

        $disabled = $events();
        static::assertCount(1, $disabled);
        static::assertSame(EndpointState::Degraded, $disabled[0]->fromState);
        static::assertSame(DisabledOrigin::Operator, $disabled[0]->origin);
    }

    /**
     * Catches: a missing echo guard on SUSPENDED. The mirrored value is already false, so writing
     * `active = false` again carries no intent. State, episode clock, and the held backlog must
     * all stay as they were, and no event may fire.
     */
    public function testDeactivatingSuspendedWebhookIsAnEchoAndChangesNothing(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 3);
        $this->seedHealth('wh', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'cooldown_until' => (new \DateTimeImmutable('+4 hours'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'suspended_since' => self::SUSPENDED_SINCE,
        ]);
        $this->seedOutboxRow('evt-held', 'wh', held: true);

        $events = $this->captureDisabledEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Suspended->value, $health['endpoint_state'], 'an echo write must not turn SUSPENDED into an operator kill');
        static::assertSame(self::SUSPENDED_SINCE, $health['suspended_since'], 'the suspension episode clock must survive the echo');
        static::assertNull($health['disabled_origin'], 'no operator provenance may be written on an echo');
        static::assertNull($health['disabled_since']);

        static::assertSame(
            WebhookEventLogDefinition::STATUS_PAUSED,
            $this->fetchDeliveryStatus('evt-held'),
            'the held backlog must stay paused — an echo must not drop it',
        );
        static::assertCount(0, $events(), 'an echo must not dispatch a WebhookDisabledEvent');
    }

    /**
     * Catches: an echo on an already-DISABLED webhook overwriting `disabled_origin`. Turning
     * `escalation` into `operator` would silently cut the webhook off from the app-update recovery
     * path that the escalation origin still allows.
     */
    public function testDeactivatingEscalationDisabledWebhookKeepsTheEscalationOrigin(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Disabled, [
            'consecutive_transient_failures' => 5,
            'disabled_since' => self::DISABLED_SINCE,
            'disabled_origin' => DisabledOrigin::Escalation->value,
        ]);

        $events = $this->captureDisabledEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Disabled->value, $health['endpoint_state']);
        static::assertSame(
            DisabledOrigin::Escalation->value,
            $health['disabled_origin'],
            'an echo on an already-DISABLED webhook must not rewrite the origin to operator',
        );
        static::assertSame(self::DISABLED_SINCE, $health['disabled_since'], 'the original disable timestamp must survive the echo');
        static::assertCount(0, $events(), 'no transition happened — no WebhookDisabledEvent may be dispatched');
    }

    /**
     * Catches: the kill not sticking on a webhook with NO health row. A never-failed webhook has
     * no row, so a late failure of a still-enqueued delivery would INSERT a fresh HEALTHY row and
     * mirror active=1, resurrecting it. The kill must insert a DISABLED row (origin operator).
     */
    public function testDeactivatingWebhookWithNoHealthRowInsertsDisabledRow(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        // deliberately no seedHealth('wh') — a never-failed webhook has no webhook_health row
        static::assertFalse($this->hasHealthRow('wh'), 'precondition: a never-failed webhook has no health row.');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(
            EndpointState::Disabled->value,
            $health['endpoint_state'],
            'Deactivating a no-health-row webhook must insert a DISABLED row so a late failure cannot resurrect active=1.',
        );
        static::assertSame(DisabledOrigin::Operator->value, $health['disabled_origin'], 'the fail-open insert carries operator provenance');
        static::assertNotNull($health['disabled_since'], 'The fail-open DISABLED insert must stamp disabled_since.');

        // The kill must absorb a late delivery failure — the exact resurrection vector above: a
        // transient failure on the now-DISABLED row must not mirror active=1 back.
        static::getContainer()->get(WebhookHealthService::class)
            ->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(
            EndpointState::Disabled->value,
            (string) $this->fetchHealthRow('wh')['endpoint_state'],
            'A late transient failure on a DISABLED webhook must be absorbed — it stays DISABLED.',
        );
        static::assertSame(
            0,
            $this->fetchWebhookActive('wh'),
            'The absorbed failure must not mirror active=1 back onto the killed webhook.',
        );
    }

    /**
     * Catches: a missing `Feature::isActive` guard. Flag-off, the legacy raw `active = 0` write
     * stands alone: no health row may be created and no event may fire.
     */
    public function testDeactivationIsNoOpUnderFlagOff(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);

        $events = $this->captureDisabledEvents();

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        static::assertSame(0, $this->fetchWebhookActive('wh'), 'the legacy raw active = 0 write stands alone');
        static::assertFalse(
            $this->hasHealthRow('wh'),
            'Flag-off, the health model must not be involved — no webhook_health row may be created.',
        );
        static::assertCount(0, $events(), 'Flag-off, the subscriber must not run — no WebhookDisabledEvent may be dispatched.');
    }

    /**
     * Collects WebhookDisabledEvent dispatches; the returned closure detaches the listener and
     * returns what was captured. Same dispatcher the service uses, so this sees the real side effect.
     *
     * @return \Closure(): list<WebhookDisabledEvent>
     */
    private function captureDisabledEvents(): \Closure
    {
        /** @var \ArrayObject<int, WebhookDisabledEvent> $captured */
        $captured = new \ArrayObject();
        $listener = static function (WebhookDisabledEvent $event) use ($captured): void {
            $captured->append($event);
        };
        $this->eventDispatcher->addListener(WebhookDisabledEvent::class, $listener);

        return function () use ($captured, $listener): array {
            $this->eventDispatcher->removeListener(WebhookDisabledEvent::class, $listener);

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
     * Seeds an outbox row through the store's own writers — claimable (`queued`) or held
     * (`paused`) — the same rows the operator kill must drop or leave alone.
     */
    private function seedOutboxRow(string $eventKey, string $webhookKey, bool $held): void
    {
        $insert = new OutboxInsert(
            webhookEventId: $this->ids->get($eventKey),
            webhookId: $this->ids->get($webhookKey),
            partitionKey: Hasher::hashBinary('default', 'xxh128'),
            serializedMessage: 'serialized-payload',
        );

        $entry = $held
            ? $this->outboxStore->recordHeldOutboxEntry($insert)
            : $this->outboxStore->recordOutboxEntry($insert);
        static::assertNotNull($entry);
    }

    /**
     * The DISABLED drop contract on one row: the webhook_delivery row is deleted and its event-log
     * row is FAILED with the `webhook_disabled` reason — payload kept for replay.
     */
    private function assertBacklogRowDropped(string $eventKey): void
    {
        static::assertFalse(
            $this->connection->fetchOne(
                'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => $this->ids->getBytes($eventKey)],
            ),
            'the kill must delete the undelivered webhook_delivery row',
        );

        $log = $this->connection->fetchAssociative(
            'SELECT delivery_status, failure_reason FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes($eventKey)],
        );
        static::assertIsArray($log);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $log['delivery_status']);
        static::assertSame(
            WebhookOutboxStore::DROP_REASON_DISABLED,
            $log['failure_reason'],
            'the dropped row must carry the webhook_disabled reason',
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
            'SELECT endpoint_state, cooldown_until, suspended_since, disabled_since, disabled_origin
             FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)],
        );
        static::assertIsArray($row);

        return $row;
    }

    private function fetchWebhookActive(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT active FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)],
        );
    }

    private function hasHealthRow(string $key): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)],
        );
    }
}
