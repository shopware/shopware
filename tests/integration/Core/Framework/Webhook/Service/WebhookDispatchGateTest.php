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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\HttpErrorClassifier;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class WebhookDispatchGateTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    /**
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
        $this->seedWebhook('wh-suspended', CustomerBeforeLoginEvent::EVENT_NAME);
        $this->seedHealth('wh-suspended', EndpointState::Suspended, [
            'cooldown_until' => (new \DateTimeImmutable('+1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $eventLogRowsBefore = $this->countTable('webhook_event_log');
        $deliveryRowsBefore = $this->countTable('webhook_delivery');

        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            $manager = $this->getManager();
            $manager->dispatch($event);
            $manager->dispatch($event);
            $manager->dispatch($event);
        });

        static::assertSame($eventLogRowsBefore, $this->countTable('webhook_event_log'));
        static::assertSame($deliveryRowsBefore, $this->countTable('webhook_delivery'));
    }

    public function testDisabledWebhookShedsEventsWritingNoRows(): void
    {
        $this->seedWebhook('wh-disabled', CustomerBeforeLoginEvent::EVENT_NAME);
        $this->seedHealth('wh-disabled', EndpointState::Disabled);

        $event = $this->createCustomerBeforeLoginEvent();
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            $this->getManager()->dispatch($event);
        });

        static::assertSame(0, $this->countTable('webhook_event_log'));
        static::assertSame(0, $this->countTable('webhook_delivery'));
    }

    public function testRoutesDegradedAndHealthyWebhooksIndependently(): void
    {
        $this->seedWebhook('wh-degraded', CustomerBeforeLoginEvent::EVENT_NAME);
        $this->seedWebhook('wh-healthy', CustomerBeforeLoginEvent::EVENT_NAME);
        $this->seedHealth('wh-degraded', EndpointState::Degraded, [
            'consecutive_transient_failures' => 5,
            'cooldown_until' => (new \DateTimeImmutable('+5 minutes'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            $this->getManager()->dispatch($event);
        });

        static::assertSame(
            [
                'wh-degraded' => WebhookEventLogDefinition::STATUS_PAUSED,
                'wh-healthy' => WebhookEventLogDefinition::STATUS_QUEUED,
            ],
            $this->connection->fetchAllKeyValue(
                'SELECT webhook.name, webhook_delivery.delivery_status
                 FROM webhook_delivery
                 INNER JOIN webhook ON webhook.id = webhook_delivery.webhook_id
                 ORDER BY webhook.name'
            )
        );

        static::assertSame(
            [
                'wh-degraded' => WebhookEventLogDefinition::STATUS_PAUSED,
                'wh-healthy' => WebhookEventLogDefinition::STATUS_QUEUED,
            ],
            $this->connection->fetchAllKeyValue(
                'SELECT webhook_name, delivery_status
                 FROM webhook_event_log
                 ORDER BY webhook_name'
            )
        );

        static::assertFalse(
            $this->connection->fetchOne(
                'SELECT 1 FROM webhook_health WHERE webhook_id = :id',
                ['id' => $this->ids->getBytes('wh-healthy')]
            )
        );
    }

    public function testLoaderWidensTheCandidateSetToSuspendedOnlyUnderTheFlag(): void
    {
        $this->seedWebhook('wh-active', CustomerBeforeLoginEvent::EVENT_NAME);
        $this->seedWebhook('wh-suspended', CustomerBeforeLoginEvent::EVENT_NAME, active: false);
        $this->seedHealth('wh-suspended', EndpointState::Suspended);
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

        $expectedFlagOn = [$this->ids->get('wh-active'), $this->ids->get('wh-suspended')];
        sort($expectedFlagOn);
        sort($flagOnIds);

        static::assertSame($expectedFlagOn, $flagOnIds);
        static::assertSame([$this->ids->get('wh-active')], $flagOffIds);
    }

    public function testLoaderIncludesDisabledWebhookOnlyUnderTheFlag(): void
    {
        $this->seedWebhook('wh-disabled', CustomerBeforeLoginEvent::EVENT_NAME, active: false);
        $this->seedHealth('wh-disabled', EndpointState::Disabled);

        $loader = static::getContainer()->get(WebhookLoader::class);

        $flagOnIds = Feature::withFeatureEnabled(
            'WEBHOOKS_REWORK',
            fn (): array => array_map(static fn (Webhook $webhook): string => $webhook->id, $loader->getWebhooks())
        );
        $flagOffIds = Feature::withFeatureDisabled(
            'WEBHOOKS_REWORK',
            fn (): array => array_map(static fn (Webhook $webhook): string => $webhook->id, $loader->getWebhooks())
        );

        static::assertSame([$this->ids->get('wh-disabled')], $flagOnIds);
        static::assertSame([], $flagOffIds);
    }

    public function testAuthStreakSuspensionLeavesSameAppSiblingHealthyAndDelivering(): void
    {
        $this->seedAppWithWebhooks(['wh-broken', 'wh-sibling'], CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/shared-endpoint');

        $health = static::getContainer()->get(WebhookHealthService::class);

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

        static::assertFalse(
            $this->connection->fetchOne(
                'SELECT 1 FROM webhook_health WHERE webhook_id = :id',
                ['id' => $this->ids->getBytes('wh-sibling')]
            )
        );

        $event = $this->createCustomerBeforeLoginEvent();
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            $this->getManager()->dispatch($event);
        });

        $siblingStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes('wh-sibling')]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $siblingStatus);
        static::assertSame(0, $this->countOutboxRowsForWebhook('wh-broken'));
    }

    public function testClaimableEscapeRowIsDeliveredOnceAndItsFailureFeedsTheStreak(): void
    {
        // Simulate a claimable row committed after the suspension pause sweep.
        $this->seedWebhook('wh-escape', CustomerBeforeLoginEvent::EVENT_NAME, active: false);
        $this->seedHealth('wh-escape', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'cooldown_until' => (new \DateTimeImmutable('+5 minutes'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->insertClaimableRow('evt-escape', 'wh-escape');

        $store = static::getContainer()->get(WebhookOutboxStore::class);
        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $claimable = [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY];

        $due = $store->fetchDue($partitionKey, $claimable, 10);
        static::assertCount(1, $due);
        static::assertSame($this->ids->get('evt-escape'), $due[0]->webhookEventId);
        static::assertNotNull($store->markRunning($this->ids->get('evt-escape')));
        static::assertSame([], $store->fetchDue($partitionKey, $claimable, 10));

        $health = static::getContainer()->get(WebhookHealthService::class);
        $result = $health->recordFailure($this->ids->get('wh-escape'), ErrorClassification::NonTransientAuth, 1);

        static::assertSame(EndpointState::Suspended, $result);
        $row = $this->connection->fetchAssociative(
            'SELECT consecutive_non_transient_failures, degraded_cycle_count FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes('wh-escape')]
        );
        static::assertNotFalse($row);
        static::assertSame(4, (int) $row['consecutive_non_transient_failures']);
        static::assertSame(0, (int) $row['degraded_cycle_count']);
    }

    // Use async delivery so queued and held rows remain observable.
    private function getManager(): WebhookManager
    {
        $isAdminWorkerEnabled = false;
        $healthService = static::getContainer()->get(WebhookHealthService::class);
        $webhookClient = new WebhookClient(
            static::getContainer()->get('shopware.webhook.guzzle'),
            static::getContainer()->get(ClockInterface::class),
        );

        $deliveryService = new WebhookDeliveryService(
            $webhookClient,
            static::getContainer()->get(AppPayloadServiceHelper::class),
            static::getContainer()->get(WebhookSigningSecretResolver::class),
            static::getContainer()->get(WebhookOutboxStore::class),
            static::getContainer()->get(RetryDelayCalculator::class),
            static::getContainer()->get('messenger.default_bus'),
            $healthService,
            static::getContainer()->get('logger'),
            new HttpErrorClassifier(),
            $isAdminWorkerEnabled,
        );

        return new WebhookManager(
            static::getContainer()->get(WebhookLoader::class),
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
            $healthService,
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
            'url' => 'https://example.com/webhook',
            'active' => (int) $active,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
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
