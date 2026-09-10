<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class ReactivateWebhooksOnAppReregistrationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SUSPENDED_SINCE = '2026-06-01 12:00:00.000';

    private const DISABLED_SINCE = '2026-06-02 12:00:00.000';

    private Connection $connection;

    private IdsCollection $ids;

    private WebhookOutboxStore $outboxStore;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->outboxStore = static::getContainer()->get(WebhookOutboxStore::class);
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');
    }

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
        // The persister writes active = 1 before AppUpdatedEvent; the operator disable must win.
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

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($appId): void {
            $this->eventDispatcher->dispatch($this->appUpdatedEvent($appId));
        });

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

        $operator = $this->fetchHealthRow('wh-operator');
        static::assertSame(EndpointState::Disabled->value, $operator['endpoint_state']);
        static::assertSame(DisabledOrigin::Operator->value, $operator['disabled_origin']);
        static::assertSame(0, $this->fetchBcColumns('wh-operator')['active']);

        static::assertSame(EndpointState::Suspended->value, $this->fetchHealthRow('wh-other')['endpoint_state']);
    }

    private function appUpdatedEvent(string $appId): AppUpdatedEvent
    {
        return new AppUpdatedEvent($this->appEntity($appId), $this->manifest(), Context::createDefaultContext());
    }

    private function appEntity(string $appId): AppEntity
    {
        $app = static::getContainer()->get('app.repository')
            ->search(new Criteria([$appId]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(AppEntity::class, $app);

        return $app;
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
            'the held delivery must become claimable',
        );
        static::assertSame(
            WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            (string) $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => $this->ids->getBytes($eventKey)],
            ),
            'the event log must mirror the delivery status',
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
