<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\Event\WebhookDegradedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Verifies that lifecycle-listener failures cannot roll back health transitions or backlog changes.
 *
 * @internal
 */
#[Package('framework')]
class WebhookHealthBestEffortEventsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const URL = 'https://endpoint.example.com/hook';
    private const DEGRADED_THRESHOLD = 5;
    private const NON_TRANSIENT_THRESHOLD = 3;

    private IdsCollection $ids;

    private Connection $connection;

    private MockClock $clock;

    private EventDispatcher $dispatcher;

    private TestHandler $logHandler;

    private WebhookHealthService $service;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-06-15 12:00:00.000'));
        $this->dispatcher = new EventDispatcher();
        $this->logHandler = new TestHandler();

        $this->service = new WebhookHealthService(
            $this->connection,
            new WebhookOutboxStore($this->connection, $this->clock),
            new HealthConfig([300, 600, 1200, 2400, 3600, 14400], self::DEGRADED_THRESHOLD, self::NON_TRANSIENT_THRESHOLD, 7),
            $this->clock,
            $this->dispatcher,
            new Logger('test', [$this->logHandler]),
        );
    }

    public function testAThrowingSuspendedListenerDoesNotBlockTheSuspensionOrTheBacklogHold(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy, cnf: self::NON_TRANSIENT_THRESHOLD - 1);
        $this->createDelivery('evt', 'wh', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->throwOn(WebhookSuspendedEvent::class);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);

        static::assertSame(EndpointState::Suspended, $result);
        $this->assertState('wh', EndpointState::Suspended);
        static::assertFalse($this->fetchActive('wh'));
        $this->assertDeliveryStatus('evt', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertListenerFailureLoggedFor(WebhookSuspendedEvent::class);
    }

    public function testAThrowingDegradedListenerDoesNotBlockTheDegradeOrTheBacklogHold(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy, ctf: self::DEGRADED_THRESHOLD - 1);
        $this->createDelivery('evt', 'wh', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->throwOn(WebhookDegradedEvent::class);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Degraded, $result);
        $this->assertState('wh', EndpointState::Degraded);
        static::assertTrue($this->fetchActive('wh'));
        $this->assertDeliveryStatus('evt', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertListenerFailureLoggedFor(WebhookDegradedEvent::class);
    }

    public function testAThrowingDisabledListenerDoesNotBlockTheOperatorKillOrTheBacklogDrop(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy);
        $this->createDelivery('evt', 'wh', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->throwOn(WebhookDisabledEvent::class);

        $killed = $this->service->disableByOperator($this->ids->get('wh'));

        static::assertSame(1, $killed);
        $this->assertState('wh', EndpointState::Disabled);
        static::assertFalse($this->fetchActive('wh'));
        static::assertFalse(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id', ['id' => $this->ids->getBytes('evt')]),
        );
        $this->assertListenerFailureLoggedFor(WebhookDisabledEvent::class);
    }

    public function testAThrowingActivatedListenerDoesNotBlockRecoveryOrTheBacklogResume(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: self::NON_TRANSIENT_THRESHOLD);
        $this->seedHealth('wh', EndpointState::Suspended, cnf: self::NON_TRANSIENT_THRESHOLD, suspendedSince: $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $this->createDelivery('evt', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->throwOn(WebhookActivatedEvent::class);

        $reactivated = $this->service->reactivate($this->ids->get('wh'), WebhookActivationTrigger::Manual);

        static::assertSame(1, $reactivated);
        $this->assertState('wh', EndpointState::Healthy);
        static::assertTrue($this->fetchActive('wh'));
        $this->assertDeliveryStatus('evt', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertListenerFailureLoggedFor(WebhookActivatedEvent::class);
    }

    /**
     * @param class-string $eventClass
     */
    private function throwOn(string $eventClass): void
    {
        $this->dispatcher->addListener($eventClass, static function () use ($eventClass): void {
            throw new \RuntimeException('listener for ' . $eventClass . ' blew up');
        });
    }

    /**
     * @param class-string $eventClass
     */
    private function assertListenerFailureLoggedFor(string $eventClass): void
    {
        $matching = array_filter(
            $this->logHandler->getRecords(),
            static fn ($record): bool => $record->message === 'Webhook lifecycle event listener failed'
                && ($record->context['event'] ?? null) === $eventClass
        );

        static::assertCount(1, $matching);
    }

    private function seedWebhook(string $key, bool $active, int $errorCount): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => self::URL,
            'active' => (int) $active,
            'error_count' => $errorCount,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function seedHealth(
        string $key,
        EndpointState $state,
        int $ctf = 0,
        int $cnf = 0,
        ?string $suspendedSince = null,
    ): void {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'consecutive_transient_failures' => $ctf,
            'consecutive_non_transient_failures' => $cnf,
            'degraded_cycle_count' => 0,
            'suspended_since' => $suspendedSince,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createDelivery(string $eventKey, string $webhookKey, string $deliveryStatus): void
    {
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => $deliveryStatus,
            'webhook_name' => 'test-hook',
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

    private function assertState(string $key, EndpointState $expected): void
    {
        static::assertSame($expected->value, $this->connection->fetchOne(
            'SELECT endpoint_state FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        ));
    }

    private function assertDeliveryStatus(string $eventKey, string $expectedStatus): void
    {
        static::assertSame($expectedStatus, $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        ));
    }

    private function fetchActive(string $key): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT active FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }
}
