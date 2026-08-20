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
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class DisableWebhookOnAdminDeactivationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SUSPENDED_SINCE = '2026-06-01 12:00:00.000';

    private Connection $connection;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $webhookRepository;

    private WebhookOutboxStore $outboxStore;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->webhookRepository = static::getContainer()->get('webhook.repository');
        $this->outboxStore = static::getContainer()->get(WebhookOutboxStore::class);
    }

    public function testDeactivatingHealthyWebhookDisablesItWithOperatorOriginAndDropsTheBacklog(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        static::assertFalse($this->hasHealthRow('wh'));
        $this->seedOutboxRow('evt-queued', 'wh', held: false);

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Disabled->value, $health['endpoint_state']);
        static::assertSame(DisabledOrigin::Operator->value, $health['disabled_origin']);
        static::assertNotNull($health['disabled_since']);
        static::assertSame(0, $this->fetchWebhookActive('wh'));

        $this->assertBacklogRowDropped('evt-queued');
    }

    public function testDeactivatingSuspendedWebhookIsAnEchoAndChangesNothing(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 3);
        $this->seedHealth('wh', EndpointState::Suspended, [
            'consecutive_non_transient_failures' => 3,
            'cooldown_until' => (new \DateTimeImmutable('+4 hours'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'suspended_since' => self::SUSPENDED_SINCE,
        ]);
        $this->seedOutboxRow('evt-held', 'wh', held: true);

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Suspended->value, $health['endpoint_state']);
        static::assertSame(self::SUSPENDED_SINCE, $health['suspended_since']);
        static::assertNull($health['disabled_origin']);
        static::assertNull($health['disabled_since']);

        static::assertSame(
            WebhookEventLogDefinition::STATUS_PAUSED,
            $this->fetchDeliveryStatus('evt-held'),
            'the held backlog must stay paused',
        );
    }

    public function testDeactivationIsNoOpUnderFlagOff(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            $this->webhookRepository->update(
                [['id' => $this->ids->get('wh'), 'active' => false]],
                Context::createDefaultContext(),
            );
        });

        static::assertSame(0, $this->fetchWebhookActive('wh'));
        static::assertFalse($this->hasHealthRow('wh'));
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

    private function assertBacklogRowDropped(string $eventKey): void
    {
        static::assertFalse(
            $this->connection->fetchOne(
                'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => $this->ids->getBytes($eventKey)],
            ),
            'the undelivered row must be deleted',
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
            'the failure reason must identify a disabled webhook',
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
