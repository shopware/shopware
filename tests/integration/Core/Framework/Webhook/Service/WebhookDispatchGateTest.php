<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\ErrorClassifier;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * Locks down the WEBHOOKS_REWORK dispatch gate ({@see WebhookManager}) with the REAL
 * {@see WebhookHealthService} as its EndpointHealth, so the Skip/Hold/Deliver decision runs
 * against the DB — unlike the sibling {@see WebhookManagerTest}, which passes a null gate.
 *
 * The gate maps health state to an outbox-row outcome:
 *  - SUSPENDED/DISABLED → Skip: the event is shed, no rows written (asserted as row counts).
 *    SUSPENDED sheds only while its cooldown runs; once it elapses with nothing held and nothing
 *    in flight, the gate admits one natural event as the half-open trial.
 *  - DEGRADED           → Hold: a `paused` (non-claimable) row on both mirrored tables.
 *  - HEALTHY / no row   → Deliver: a `queued` (claimable) row; the gate never writes health.
 *
 * Also pinned: the flag-on {@see WebhookLoader} lets SUSPENDED/DISABLED webhooks (mirrored
 * `active = 0`) still reach the gate; a suspension stays on the failing webhook (no sibling blast
 * radius); and a claimable row that escaped the suspension's pause sweep is delivered once, its
 * result counting as ordinary evidence.
 *
 * Wiring mirrors {@see WebhookDispatchEndToEndTest}: built on `messenger.default_bus` with
 * isAdminWorkerEnabled=false so the Deliver lane goes through the real WebhookTransport, which
 * persists the outbox row. QueueTestBehaviour does not roll back, so each test cleans the webhook
 * tables itself and deletes its app fixtures in tearDown.
 *
 * @internal
 */
class WebhookDispatchGateTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    /**
     * Seeded app-fixture rows (table => list of binary PKs), removed in tearDown so no app,
     * acl_role, or integration rows leak into the shared test DB.
     *
     * @var array<string, list<string>>
     */
    private array $appFixtureIds = [];

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->appFixtureIds = [];
        $this->cleanupWebhookTables();
    }

    protected function tearDown(): void
    {
        $this->cleanupWebhookTables();
        $this->cleanupAppFixtures();
    }

    public function testSuspendedWebhookShedsEventsWritingNoRows(): void
    {
        // Non-app webhook: isEventDispatchingAllowed() is always true (appId === null), so every
        // dispatch reaches the gate's Skip decision. The cooldown must still be running — once
        // elapsed with nothing held, the gate would admit a natural trial instead of shedding.
        $this->seedWebhook('wh-suspended', CustomerBeforeLoginEvent::EVENT_NAME);
        $this->seedHealth('wh-suspended', EndpointState::Suspended, [
            'cooldown_until' => (new \DateTimeImmutable('+1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $eventLogRowsBefore = $this->countTable('webhook_event_log');
        $deliveryRowsBefore = $this->countTable('webhook_delivery');

        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            $manager = $this->getManager();
            // Repeated dispatches: the log must not grow while the webhook sheds.
            $manager->dispatch($event);
            $manager->dispatch($event);
            $manager->dispatch($event);
        });

        static::assertSame(
            $eventLogRowsBefore,
            $this->countTable('webhook_event_log'),
            'A SUSPENDED webhook must shed its events — no webhook_event_log row may be written.'
        );
        static::assertSame(
            $deliveryRowsBefore,
            $this->countTable('webhook_delivery'),
            'A SUSPENDED webhook must shed its events — no webhook_delivery row may be written.'
        );
    }

    public function testDisabledWebhookShedsEventsWritingNoRows(): void
    {
        $this->seedWebhook('wh-disabled', CustomerBeforeLoginEvent::EVENT_NAME);
        $this->seedHealth('wh-disabled', EndpointState::Disabled);

        $eventLogRowsBefore = $this->countTable('webhook_event_log');
        $deliveryRowsBefore = $this->countTable('webhook_delivery');

        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            $this->getManager()->dispatch($event);
        });

        static::assertSame(
            $eventLogRowsBefore,
            $this->countTable('webhook_event_log'),
            'A DISABLED webhook must shed its events through the same Skip arm as SUSPENDED.'
        );
        static::assertSame($deliveryRowsBefore, $this->countTable('webhook_delivery'));
    }

    public function testDegradedWebhookHoldsEventAsPausedRow(): void
    {
        $this->seedWebhook('wh-degraded', CustomerBeforeLoginEvent::EVENT_NAME);
        // Mid-cooldown DEGRADED — the normal shape while the breaker waits for the next trial.
        $this->seedHealth('wh-degraded', EndpointState::Degraded, [
            'consecutive_transient_failures' => 5,
            'cooldown_until' => (new \DateTimeImmutable('+5 minutes'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            $this->getManager()->dispatch($event);
        });

        $deliveryStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes('wh-degraded')]
        );

        static::assertSame(
            WebhookEventLogDefinition::STATUS_PAUSED,
            $deliveryStatus,
            'A DEGRADED webhook must hold the event as a paused (non-claimable) row, not queue it.'
        );

        // The hold is mirrored onto the event log, the second table the read path joins.
        $eventLogStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'wh-degraded']
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_PAUSED, $eventLogStatus);
    }

    public function testHealthyWebhookDispatchesNormally(): void
    {
        // No health row at all: a missing row reads as HEALTHY (fail-open).
        $this->seedWebhook('wh-healthy', CustomerBeforeLoginEvent::EVENT_NAME);

        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            // isAdminWorkerEnabled=false: the Deliver lane goes via the bus and the transport writes
            // a claimable `queued` row (true would deliver inline and fire a live HTTP request).
            $this->getManager(isAdminWorkerEnabled: false)->dispatch($event);
        });

        $deliveryStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes('wh-healthy')]
        );

        static::assertSame(
            WebhookEventLogDefinition::STATUS_QUEUED,
            $deliveryStatus,
            'A HEALTHY webhook must write a claimable (queued) row.'
        );

        // The gate only reads health: a healthy delivery must not create a webhook_health row
        // (DBAL fetchOne returns false when no row matches).
        static::assertFalse(
            $this->connection->fetchOne(
                'SELECT 1 FROM webhook_health WHERE webhook_id = :id',
                ['id' => $this->ids->getBytes('wh-healthy')]
            ),
            'A healthy delivery must not create or touch a webhook_health row via the gate.'
        );
    }

    public function testLoaderWidensTheCandidateSetToSuspendedAndDisabledOnlyUnderTheFlag(): void
    {
        $this->seedWebhook('wh-active', CustomerBeforeLoginEvent::EVENT_NAME);
        // SUSPENDED/DISABLED mirror active = 0 — only their health row qualifies them flag-on.
        $this->seedWebhook('wh-suspended', CustomerBeforeLoginEvent::EVENT_NAME, active: false);
        $this->seedHealth('wh-suspended', EndpointState::Suspended);
        $this->seedWebhook('wh-disabled', CustomerBeforeLoginEvent::EVENT_NAME, active: false);
        $this->seedHealth('wh-disabled', EndpointState::Disabled);
        // Inactive without a health row: a plain legacy deactivation, excluded under both flags.
        $this->seedWebhook('wh-inactive', CustomerBeforeLoginEvent::EVENT_NAME, active: false);

        $loader = static::getContainer()->get(WebhookLoader::class);

        $flagOnIds = Feature::withFeatureEnabled(
            'WEBHOOKS_REWORK',
            fn (): array => array_map(static fn (Webhook $webhook): string => $webhook->id, $loader->getWebhooks())
        );
        $flagOffIds = Feature::withFeatureDisabled(
            'WEBHOOKS_REWORK',
            fn (): array => array_map(static fn (Webhook $webhook): string => $webhook->id, $loader->getWebhooks())
        );

        $expectedFlagOn = [$this->ids->get('wh-active'), $this->ids->get('wh-suspended'), $this->ids->get('wh-disabled')];
        sort($expectedFlagOn);
        sort($flagOnIds);

        static::assertSame(
            $expectedFlagOn,
            $flagOnIds,
            'Flag-on, SUSPENDED/DISABLED webhooks must reach the gate despite active = 0 — recovery rides natural traffic.'
        );
        static::assertSame(
            [$this->ids->get('wh-active')],
            $flagOffIds,
            'Flag-off must keep trunk\'s exact active = 1 candidate set.'
        );
    }

    public function testAuthStreakSuspensionLeavesSameAppSiblingHealthyAndDelivering(): void
    {
        // Two webhooks of the SAME app on the SAME event + URL — the shape trunk's RelatedWebhooks
        // propagation used to punish collectively.
        $this->seedAppWithWebhooks(['wh-broken', 'wh-sibling'], CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/shared-endpoint');

        $health = static::getContainer()->get(WebhookHealthService::class);

        // Three 401s in a row reach the auth-streak threshold and suspend this webhook.
        $health->recordFailure($this->ids->get('wh-broken'), ErrorClassification::NonTransientAuth, 1);
        $health->recordFailure($this->ids->get('wh-broken'), ErrorClassification::NonTransientAuth, 1);
        $health->recordFailure($this->ids->get('wh-broken'), ErrorClassification::NonTransientAuth, 1);

        static::assertSame(
            EndpointState::Suspended->value,
            $this->connection->fetchOne(
                'SELECT endpoint_state FROM webhook_health WHERE webhook_id = :id',
                ['id' => $this->ids->getBytes('wh-broken')]
            )
        );

        // The suspension is per-webhook: the sibling's health row was never touched…
        static::assertFalse(
            $this->connection->fetchOne(
                'SELECT 1 FROM webhook_health WHERE webhook_id = :id',
                ['id' => $this->ids->getBytes('wh-sibling')]
            ),
            'Suspending one webhook must not create or touch the same-app sibling\'s health row.'
        );

        // …and the sibling keeps delivering while the broken one sheds.
        $event = $this->createCustomerBeforeLoginEvent();
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            $this->getManager()->dispatch($event);
        });

        $siblingStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes('wh-sibling')]
        );
        static::assertSame(
            WebhookEventLogDefinition::STATUS_QUEUED,
            $siblingStatus,
            'The same-app sibling (same event + URL) must keep delivering — no sibling propagation.'
        );
        static::assertSame(0, $this->countOutboxRowsForWebhook('wh-broken'));
    }

    public function testClaimableEscapeRowIsDeliveredOnceAndItsFailureFeedsTheStreak(): void
    {
        // The gate decides at a point in time: a row written claimable in the instant its webhook
        // suspended escapes the transition's pause sweep. Seeded here as if post-sweep: the webhook
        // is already SUSPENDED (cooldown running), the escaped row sits claimable.
        $this->seedWebhook('wh-escape', CustomerBeforeLoginEvent::EVENT_NAME, active: false);
        $this->seedHealth('wh-escape', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'cooldown_until' => (new \DateTimeImmutable('+5 minutes'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->insertClaimableRow('evt-escape', 'wh-escape');

        $store = static::getContainer()->get(WebhookOutboxStore::class);
        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $claimable = [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY];

        // The unmodified transport delivers the escaped row — once.
        $due = $store->fetchDue($partitionKey, $claimable, 10);
        static::assertCount(1, $due, 'the escaped claimable row must be delivered, not stranded');
        static::assertSame($this->ids->get('evt-escape'), $due[0]->webhookEventId);
        static::assertNotNull($store->markRunning($this->ids->get('evt-escape')));
        static::assertSame([], $store->fetchDue($partitionKey, $claimable, 10), 'one delivery, never a second claim');

        // Its 401 result is ordinary evidence: the auth streak counts, and the still-running
        // cooldown means the ladder does not move.
        $health = static::getContainer()->get(WebhookHealthService::class);
        $result = $health->recordFailure($this->ids->get('wh-escape'), ErrorClassification::NonTransientAuth, 1);

        static::assertSame(EndpointState::Suspended, $result);
        $row = $this->connection->fetchAssociative(
            'SELECT consecutive_non_transient_failures, degraded_cycle_count FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes('wh-escape')]
        );
        static::assertNotFalse($row);
        static::assertSame(4, (int) $row['consecutive_non_transient_failures'], 'the escaped delivery\'s failure must feed the streak like any delivery');
        static::assertSame(0, (int) $row['degraded_cycle_count'], 'a result during a running cooldown must not climb the ladder');
    }

    /**
     * Builds a WebhookManager with the REAL WebhookHealthService as its gate, plus a fresh
     * WebhookDeliveryService on the same bus + admin-worker flag. The container-wired service would
     * read the test env's enabled admin worker and force inline sync delivery.
     */
    private function getManager(bool $isAdminWorkerEnabled = false): WebhookManager
    {
        $endpointHealth = static::getContainer()->get(WebhookHealthService::class);
        $webhookClient = new WebhookClient(
            static::getContainer()->get('shopware.webhook.guzzle'),
            static::getContainer()->get(ClockInterface::class),
        );

        $deliveryService = new WebhookDeliveryService(
            $webhookClient,
            static::getContainer()->get(AppPayloadServiceHelper::class),
            static::getContainer()->get(WebhookOutboxStore::class),
            static::getContainer()->get(RetryDelayCalculator::class),
            static::getContainer()->get('messenger.default_bus'),
            $endpointHealth,
            static::getContainer()->get('logger'),
            $endpointHealth,
            static::getContainer()->get(ErrorClassifier::class),
            $isAdminWorkerEnabled,
        );

        return new WebhookManager(
            static::getContainer()->get(WebhookLoader::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(HookableEventFactory::class),
            static::getContainer()->get(AppLocaleProvider::class),
            static::getContainer()->get(AppPayloadServiceHelper::class),
            $webhookClient,
            static::getContainer()->get('messenger.default_bus'),
            $_SERVER['APP_URL'],
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $isAdminWorkerEnabled,
            $deliveryService,
            static::getContainer()->get(WebhookOutboxStore::class),
            $endpointHealth,
        );
    }

    private function createCustomerBeforeLoginEvent(): CustomerBeforeLoginEvent
    {
        return new CustomerBeforeLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );
    }

    private function seedWebhook(string $key, string $eventName, bool $active = true): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => $eventName,
            'url' => 'https://example.com/' . $key,
            'active' => (int) $active,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * One app carrying the given webhooks on one shared event + URL. The empty acl_role privileges
     * are fine for CustomerBeforeLoginEvent (a single scalar, so isAllowed passes). Fixture rows
     * are tracked for tearDown.
     *
     * @param list<string> $webhookKeys
     */
    private function seedAppWithWebhooks(array $webhookKeys, string $eventName, string $url): void
    {
        $unique = Uuid::randomHex();
        $aclRoleId = Uuid::randomBytes();
        $integrationId = Uuid::randomBytes();
        $appId = Uuid::randomBytes();
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('acl_role', [
            'id' => $aclRoleId,
            'name' => 'role-' . $unique,
            'privileges' => json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
        $this->appFixtureIds['acl_role'][] = $aclRoleId;

        $this->connection->insert('integration', [
            'id' => $integrationId,
            'access_key' => 'key-' . $unique,
            'secret_access_key' => 'secret-' . $unique,
            'label' => 'integration-' . $unique,
            'created_at' => $now,
        ]);
        $this->appFixtureIds['integration'][] = $integrationId;

        $this->connection->insert('app', [
            'id' => $appId,
            'name' => 'app-' . $unique,
            'path' => '/dev/null',
            'version' => '1.0.0',
            'active' => 1,
            'app_secret' => 'app-secret-' . $unique,
            'integration_id' => $integrationId,
            'acl_role_id' => $aclRoleId,
            'created_at' => $now,
        ]);
        $this->appFixtureIds['app'][] = $appId;

        foreach ($webhookKeys as $key) {
            $this->connection->insert('webhook', [
                'id' => $this->ids->getBytes($key),
                'name' => $key,
                'event_name' => $eventName,
                'url' => $url,
                'active' => 1,
                'app_id' => $appId,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function seedHealth(string $key, EndpointState $state, array $extra = []): void
    {
        $defaults = [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        if ($state === EndpointState::Suspended) {
            $defaults['suspended_since'] = (new \DateTimeImmutable('-1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        if ($state === EndpointState::Disabled) {
            $defaults['disabled_since'] = (new \DateTimeImmutable('-1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $defaults['disabled_origin'] = DisabledOrigin::Escalation->value;
        }

        $this->connection->insert('webhook_health', array_merge($defaults, $extra));
    }

    /**
     * Seeds the claimable row pair a racing gate leaves behind when its Deliver decision commits
     * just after the suspension's pause sweep ran.
     */
    private function insertClaimableRow(string $eventKey, string $webhookKey): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhook_name' => $webhookKey,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://example.com/' . $webhookKey,
            'serialized_webhook_message' => 'serialized-payload',
            'created_at' => $now,
        ]);

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => $this->ids->getBytes($webhookKey),
            'partition_key' => Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128'),
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'execution_count' => 0,
            'created_at' => $now,
        ]);
    }

    private function countTable(string $table): int
    {
        return (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', $table));
    }

    private function countOutboxRowsForWebhook(string $key): int
    {
        $deliveries = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
        $logs = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => $key]
        );

        return $deliveries + $logs;
    }

    private function cleanupWebhookTables(): void
    {
        $this->connection->executeStatement('DELETE FROM webhook_stream');
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook_health');
        $this->connection->executeStatement('DELETE FROM webhook');
    }

    /**
     * Deletes exactly the app/acl_role/integration rows this test seeded, children first (app
     * references both others). The webhook rows are already gone via cleanupWebhookTables.
     */
    private function cleanupAppFixtures(): void
    {
        foreach (['app', 'integration', 'acl_role'] as $table) {
            foreach ($this->appFixtureIds[$table] ?? [] as $id) {
                $this->connection->executeStatement(
                    \sprintf('DELETE FROM %s WHERE id = :id', $table),
                    ['id' => $id]
                );
            }
        }

        $this->appFixtureIds = [];
    }
}
