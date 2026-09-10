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
use Shopware\Core\Framework\Log\Package;
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
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
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
        static::assertSame(EndpointState::Disabled->value, $health['endpoint_state']);
        static::assertSame(DisabledOrigin::Operator->value, $health['disabled_origin']);
        static::assertNotNull($health['disabled_since']);
        static::assertSame(0, $this->fetchWebhookActive('wh'));

        $this->assertBacklogRowDropped('evt-queued');

        $disabled = $events();
        static::assertCount(1, $disabled);
        static::assertSame($this->ids->get('wh'), $disabled[0]->webhookId);
        static::assertSame(EndpointState::Healthy, $disabled[0]->fromState);
        static::assertSame(DisabledOrigin::Operator, $disabled[0]->origin);
    }

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
        static::assertSame(EndpointState::Disabled->value, $health['endpoint_state']);
        static::assertSame(DisabledOrigin::Operator->value, $health['disabled_origin']);
        static::assertNull($health['cooldown_until']);

        $this->assertBacklogRowDropped('evt-held');

        $disabled = $events();
        static::assertCount(1, $disabled);
        static::assertSame(EndpointState::Degraded, $disabled[0]->fromState);
        static::assertSame(DisabledOrigin::Operator, $disabled[0]->origin);
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

        $events = $this->captureDisabledEvents();

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
        );
        static::assertCount(0, $events());
    }

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
        );
        static::assertSame(self::DISABLED_SINCE, $health['disabled_since']);
        static::assertCount(0, $events());
    }

    public function testDeactivatingWebhookWithNoHealthRowInsertsDisabledRow(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        static::assertFalse($this->hasHealthRow('wh'));

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

        // A late delivery result must not recreate a healthy row or repair the active mirror.
        static::getContainer()->get(WebhookHealthService::class)
            ->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(
            EndpointState::Disabled->value,
            (string) $this->fetchHealthRow('wh')['endpoint_state'],
        );
        static::assertSame(0, $this->fetchWebhookActive('wh'));
    }

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

        static::assertSame(0, $this->fetchWebhookActive('wh'));
        static::assertFalse($this->hasHealthRow('wh'));
        static::assertCount(0, $events());
    }

    /**
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
