<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Subscriber\ReactivateWebhooksOnAppReregistrationSubscriber;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Locks down the app re-registration reset: {@see ReactivateWebhooksOnAppReregistrationSubscriber}
 * routes `AppInstalledEvent`/`AppUpdatedEvent` into `reactivateForApp` under `WEBHOOKS_REWORK`.
 * This matters because an app vendor who fixed their endpoint and shipped an update would
 * otherwise stay suspended forever — auth-suspended endpoints never recover on traffic, and this
 * is the only automatic way back from escalation-DISABLED. The app's DEGRADED, SUSPENDED, and
 * escalation-DISABLED webhooks reset to HEALTHY (held rows resumed, mirror repaired, one
 * {@see WebhookActivatedEvent} each with trigger `app_reset`), while an operator-disabled webhook
 * survives — a merchant's explicit kill outlives a routine app update — and other apps' webhooks
 * stay untouched.
 *
 * @internal
 */
class ReactivateWebhooksOnAppReregistrationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SUSPENDED_SINCE = '2026-06-01 12:00:00.000';

    private const DISABLED_SINCE = '2026-06-02 12:00:00.000';

    private Connection $connection;

    private IdsCollection $ids;

    private EventDispatcherInterface $eventDispatcher;

    private WebhookOutboxStore $outboxStore;

    private ReactivateWebhooksOnAppReregistrationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');
        $this->outboxStore = static::getContainer()->get(WebhookOutboxStore::class);
        $this->subscriber = static::getContainer()->get(ReactivateWebhooksOnAppReregistrationSubscriber::class);
    }

    /**
     * Catches: the reset missing one of the recoverable states (DEGRADED, SUSPENDED,
     * escalation-DISABLED), stranding the held backlog or episode markers, leaking into another
     * app's webhooks — or undoing the operator's explicit kill.
     */
    public function testAppUpdateResetsTheAppsNonHealthyWebhooksSparingTheOperatorKill(): void
    {
        $appId = $this->createApp('SwagReregApp');
        $otherAppId = $this->createApp('SwagOtherApp');

        $this->seedWebhook('wh-degraded', $appId, active: true, errorCount: 5);
        $this->seedHealth('wh-degraded', EndpointState::Degraded, [
            'consecutive_transient_failures' => 5,
            'degraded_cycle_count' => 2,
            'cooldown_until' => (new \DateTimeImmutable('+5 minutes'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->seedWebhook('wh-suspended', $appId, active: false, errorCount: 3);
        $this->seedHealth('wh-suspended', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'cooldown_until' => (new \DateTimeImmutable('+4 hours'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'suspended_since' => self::SUSPENDED_SINCE,
        ]);
        $this->seedHeldRow('evt-held', 'wh-suspended');
        $this->seedWebhook('wh-escalation', $appId, active: false, errorCount: 3);
        $this->seedHealth('wh-escalation', EndpointState::Disabled, [
            'consecutive_non_transient_failures' => 3,
            'disabled_since' => self::DISABLED_SINCE,
            'disabled_origin' => DisabledOrigin::Escalation->value,
        ]);
        // active: true mimics the WebhookPersister, which writes active = 1 on every webhook of
        // the app right before AppUpdatedEvent fires — the reset must derive it back to 0.
        $this->seedWebhook('wh-operator', $appId, active: true, errorCount: 0);
        $this->seedHealth('wh-operator', EndpointState::Disabled, [
            'disabled_since' => self::DISABLED_SINCE,
            'disabled_origin' => DisabledOrigin::Operator->value,
        ]);
        $this->seedWebhook('wh-other', $otherAppId, active: false, errorCount: 3);
        $this->seedHealth('wh-other', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'suspended_since' => self::SUSPENDED_SINCE,
        ]);

        $events = $this->captureActivatedEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($appId): void {
            $this->subscriber->reactivate($this->appUpdatedEvent($appId));
        });

        // The recoverable trio gets a clean slate: HEALTHY, every episode marker cleared, the
        // mirror back at active = 1 / error_count = 0.
        foreach (['wh-degraded', 'wh-suspended', 'wh-escalation'] as $key) {
            $health = $this->fetchHealthRow($key);
            static::assertSame(EndpointState::Healthy->value, $health['endpoint_state'], $key);
            static::assertSame(0, (int) $health['consecutive_transient_failures'], $key);
            static::assertSame(0, (int) $health['consecutive_non_transient_failures'], $key);
            static::assertSame(0, (int) $health['degraded_cycle_count'], $key);
            static::assertNull($health['cooldown_until'], $key);
            static::assertNull($health['suspended_since'], $key);
            static::assertNull($health['disabled_since'], $key);
            static::assertNull($health['disabled_origin'], $key);
            static::assertSame(['active' => 1, 'error_count' => 0], $this->fetchBcColumns($key), $key);
        }

        $this->assertHeldRowResumed('evt-held');

        // The merchant's explicit kill survives the routine app update.
        $operator = $this->fetchHealthRow('wh-operator');
        static::assertSame(EndpointState::Disabled->value, $operator['endpoint_state']);
        static::assertSame(DisabledOrigin::Operator->value, $operator['disabled_origin']);
        static::assertSame(0, $this->fetchBcColumns('wh-operator')['active']);

        // Another app's suspended webhook is not this update's business.
        static::assertSame(EndpointState::Suspended->value, $this->fetchHealthRow('wh-other')['endpoint_state']);

        $activated = $events();
        static::assertCount(3, $activated, 'one WebhookActivatedEvent per reset webhook — the operator kill and the other app emit none');
        $byWebhookId = [];
        foreach ($activated as $event) {
            $byWebhookId[$event->webhookId] = $event;
            static::assertSame(WebhookActivationTrigger::AppReset, $event->trigger);
        }
        static::assertEqualsCanonicalizing(
            [$this->ids->get('wh-degraded'), $this->ids->get('wh-suspended'), $this->ids->get('wh-escalation')],
            array_keys($byWebhookId),
        );
        static::assertSame(EndpointState::Degraded, $byWebhookId[$this->ids->get('wh-degraded')]->fromState);
        static::assertSame(EndpointState::Suspended, $byWebhookId[$this->ids->get('wh-suspended')]->fromState);
        static::assertSame(EndpointState::Disabled, $byWebhookId[$this->ids->get('wh-escalation')]->fromState);
        static::assertSame(
            self::SUSPENDED_SINCE,
            $byWebhookId[$this->ids->get('wh-suspended')]->clearedSuspendedSince?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'the event must carry the suspended_since value this recovery cleared',
        );
    }

    /**
     * Catches: the reset listening only to app updates. A re-install is the same deliberate
     * config refresh and must heal the same way.
     */
    public function testAppInstallResetsASuspendedWebhook(): void
    {
        $appId = $this->createApp('SwagInstallApp');
        $this->seedWebhook('wh', $appId, active: false, errorCount: 3);
        $this->seedHealth('wh', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'suspended_since' => self::SUSPENDED_SINCE,
        ]);

        $events = $this->captureActivatedEvents();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($appId): void {
            $this->subscriber->reactivate(new AppInstalledEvent($this->appEntity($appId), $this->manifest(), Context::createDefaultContext()));
        });

        static::assertSame(EndpointState::Healthy->value, $this->fetchHealthRow('wh')['endpoint_state']);

        $activated = $events();
        static::assertCount(1, $activated);
        static::assertSame($this->ids->get('wh'), $activated[0]->webhookId);
        static::assertSame(WebhookActivationTrigger::AppReset, $activated[0]->trigger);
        static::assertSame(EndpointState::Suspended, $activated[0]->fromState);
    }

    /**
     * Catches: a missing `Feature::isActive` guard. Flag-off there is no health model to reset:
     * the row and the held backlog stay untouched, no event fires.
     */
    public function testAppUpdateIsNoOpUnderFlagOff(): void
    {
        $appId = $this->createApp('SwagFlagOffApp');
        $this->seedWebhook('wh', $appId, active: false, errorCount: 3);
        $this->seedHealth('wh', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'suspended_since' => self::SUSPENDED_SINCE,
        ]);
        $this->seedHeldRow('evt-held', 'wh');

        $events = $this->captureActivatedEvents();

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($appId): void {
            $this->subscriber->reactivate($this->appUpdatedEvent($appId));
        });

        static::assertCount(0, $events(), 'flag-off, the subscriber must not run — no WebhookActivatedEvent may be dispatched');
        static::assertSame(EndpointState::Suspended->value, $this->fetchHealthRow('wh')['endpoint_state']);
        static::assertSame(
            WebhookEventLogDefinition::STATUS_PAUSED,
            $this->fetchDeliveryStatus('evt-held'),
            'flag-off, the held backlog must stay paused',
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

    private function appUpdatedEvent(string $appId): AppUpdatedEvent
    {
        return new AppUpdatedEvent($this->appEntity($appId), $this->manifest(), Context::createDefaultContext());
    }

    /**
     * The subscriber reads only the app id off the event; the entity mirrors the persisted app.
     */
    private function appEntity(string $appId): AppEntity
    {
        return (new AppEntity())->assign(['id' => $appId]);
    }

    private function manifest(): Manifest
    {
        return Manifest::createFromXmlFile(__DIR__ . '/../../App/Manifest/_fixtures/minimal/manifest.xml');
    }

    private function createApp(string $name): string
    {
        $appId = Uuid::randomHex();
        static::getContainer()->get('app.repository')->create([[
            'id' => $appId,
            'name' => $name,
            'path' => 'custom/apps/' . $name,
            'active' => true,
            'version' => '1.0.0',
            'label' => $name,
            'integration' => ['label' => $name, 'accessKey' => Uuid::randomHex(), 'secretAccessKey' => Uuid::randomHex()],
            'aclRole' => ['name' => $name],
        ]], Context::createDefaultContext());

        return $appId;
    }

    private function seedWebhook(string $key, string $appId, bool $active, int $errorCount): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://example.com/' . $key,
            'app_id' => Uuid::fromHexToBytes($appId),
            'active' => (int) $active,
            'error_count' => $errorCount,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
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
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], $extra));
    }

    /**
     * Seeds a held (`paused`) outbox row through the store's own Hold writer — the same row a
     * DEGRADED/SUSPENDED hold leaves behind for the reset to resume.
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
            'the reset must resume the held backlog — the paused delivery row becomes claimable',
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
