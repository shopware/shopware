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
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
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
        static::assertSame(EndpointState::Healthy->value, $health['endpoint_state']);
        static::assertSame(0, (int) $health['consecutive_transient_failures']);
        static::assertSame(0, (int) $health['consecutive_non_transient_failures']);
        static::assertSame(0, (int) $health['degraded_cycle_count']);
        static::assertNull($health['cooldown_until']);
        static::assertNull($health['suspended_since']);

        $webhook = $this->fetchBcColumns('wh');
        static::assertSame(1, $webhook['active']);
        static::assertSame(0, $webhook['error_count']);

        $this->assertHeldRowResumed('evt-held');

        $activated = $events();
        static::assertCount(1, $activated);
        static::assertSame($this->ids->get('wh'), $activated[0]->webhookId);
        static::assertSame(EndpointState::Suspended, $activated[0]->fromState);
        static::assertSame(WebhookActivationTrigger::Manual, $activated[0]->trigger);
        static::assertSame(
            self::SUSPENDED_SINCE,
            $activated[0]->clearedSuspendedSince?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        );
    }

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
        static::assertSame(EndpointState::Healthy->value, $health['endpoint_state']);
        static::assertSame(0, (int) $health['consecutive_transient_failures']);
        static::assertNull($health['disabled_since']);
        static::assertNull($health['disabled_origin']);

        $webhook = $this->fetchBcColumns('wh');
        static::assertSame(1, $webhook['active']);
        static::assertSame(0, $webhook['error_count']);

        $activated = $events();
        static::assertCount(1, $activated);
        static::assertSame(EndpointState::Disabled, $activated[0]->fromState);
        static::assertSame(WebhookActivationTrigger::Manual, $activated[0]->trigger);
        static::assertNull($activated[0]->clearedSuspendedSince);
    }

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
        static::assertSame(EndpointState::Degraded->value, $health['endpoint_state']);
        static::assertSame(4, (int) $health['consecutive_transient_failures']);
        static::assertSame(1, (int) $health['degraded_cycle_count']);
        static::assertSame($cooldown, $health['cooldown_until']);
        static::assertSame('paused', $this->fetchDeliveryStatus('held-event'));
        static::assertCount(0, $events());
    }

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

        static::assertCount(0, $events());
        static::assertSame(EndpointState::Healthy->value, (string) $this->fetchHealthRow('wh')['endpoint_state']);

        $webhook = $this->fetchBcColumns('wh');
        static::assertSame(1, $webhook['active']);
        static::assertSame(0, $webhook['error_count']);

        $this->assertHeldRowResumed('evt-stranded');
    }

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

        static::assertCount(0, $events());
        static::assertSame(
            EndpointState::Suspended->value,
            (string) $this->fetchHealthRow('wh')['endpoint_state'],
        );
        static::assertSame(
            WebhookEventLogDefinition::STATUS_PAUSED,
            $this->fetchDeliveryStatus('evt-held'),
        );
    }

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

        static::assertCount(0, $events());
        static::assertSame(
            EndpointState::Suspended->value,
            (string) $this->fetchHealthRow('wh')['endpoint_state'],
        );
    }

    /**
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
        );
        static::assertSame(
            WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            (string) $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => $this->ids->getBytes($eventKey)],
            ),
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
