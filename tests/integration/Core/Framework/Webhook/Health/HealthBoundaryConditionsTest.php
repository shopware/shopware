<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
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
 * Covers the inclusive cooldown and retirement bounds and the exclusive held-row grace bound.
 *
 * @internal
 */
#[Package('framework')]
class HealthBoundaryConditionsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const URL = 'https://endpoint.example.com/hook';

    private const COOLDOWN_SCHEDULE = [300, 600, 1200, 2400, 3600, 14400];
    private const COOLDOWN_TIER_1 = 600;
    private const DEGRADED_THRESHOLD = 5;
    private const NON_TRANSIENT_THRESHOLD = 3;
    private const MAX_SUSPENDED_DAYS = 7;

    private IdsCollection $ids;

    private Connection $connection;

    private MockClock $clock;

    private WebhookHealthService $service;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-06-15 12:00:00.000'));

        $this->service = new WebhookHealthService(
            $this->connection,
            new WebhookOutboxStore($this->connection, $this->clock),
            new HealthConfig(self::COOLDOWN_SCHEDULE, self::DEGRADED_THRESHOLD, self::NON_TRANSIENT_THRESHOLD, self::MAX_SUSPENDED_DAYS),
            $this->clock,
            new EventDispatcher(),
            new NullLogger(),
        );
    }

    public function testCooldownExactlyAtNowCountsAsElapsedAndAdvancesTheLadder(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: self::DEGRADED_THRESHOLD);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: self::DEGRADED_THRESHOLD, cycle: 0, cooldownUntil: $this->instant('now'));

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Degraded, $result);
        static::assertSame(1, $this->fetchCycle('wh'));
        static::assertSame(
            $this->instant(\sprintf('+%d seconds', self::COOLDOWN_TIER_1)),
            $this->fetchHealthColumn('wh', 'cooldown_until'),
        );
    }

    public function testCooldownOneMillisecondInTheFutureIsAStragglerAndDoesNotAdvance(): void
    {
        $cooldown = $this->instant('+1 millisecond');
        $this->seedWebhook('wh', active: true, errorCount: self::DEGRADED_THRESHOLD);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: self::DEGRADED_THRESHOLD, cycle: 0, cooldownUntil: $cooldown);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Degraded, $result);
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertSame($cooldown, $this->fetchHealthColumn('wh', 'cooldown_until'));
    }

    public function testSuspensionExactlyAtTheSevenDayBoundRetires(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: self::NON_TRANSIENT_THRESHOLD);
        $this->seedHealth(
            'wh',
            EndpointState::Suspended,
            cnf: self::NON_TRANSIENT_THRESHOLD,
            cooldownUntil: $this->instant('+1 hour'),
            suspendedSince: $this->instant(\sprintf('-%d days', self::MAX_SUSPENDED_DAYS)),
        );

        $this->service->tick();

        $this->assertState('wh', EndpointState::Disabled);
        static::assertNotNull($this->fetchHealthColumn('wh', 'disabled_since'));
    }

    public function testSuspensionOneMillisecondInsideTheBoundSurvives(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: self::NON_TRANSIENT_THRESHOLD);
        $this->seedHealth(
            'wh',
            EndpointState::Suspended,
            cnf: self::NON_TRANSIENT_THRESHOLD,
            cooldownUntil: $this->instant('+1 hour'),
            suspendedSince: $this->instant(\sprintf('-%d days +1 millisecond', self::MAX_SUSPENDED_DAYS)),
        );

        $this->service->tick();

        $this->assertState('wh', EndpointState::Suspended);
        static::assertNull($this->fetchHealthColumn('wh', 'disabled_since'));
    }

    public function testHeldRowExactlyAtTheGraceAgeSurvivesTheResume(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: self::DEGRADED_THRESHOLD);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: self::DEGRADED_THRESHOLD, cooldownUntil: $this->instant('+1 hour'));
        $this->createHeldDelivery('evt', 'wh', $this->instant(\sprintf('-%d hours', WebhookOutboxStore::HELD_GRACE_AGE_HOURS)));

        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Healthy);
        $this->assertDeliveryStatus('evt', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
    }

    public function testHeldRowOneMillisecondPastTheGraceAgeIsCancelledOnResume(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: self::DEGRADED_THRESHOLD);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: self::DEGRADED_THRESHOLD, cooldownUntil: $this->instant('+1 hour'));
        $this->createHeldDelivery('evt', 'wh', $this->instant(\sprintf('-%d hours -1 millisecond', WebhookOutboxStore::HELD_GRACE_AGE_HOURS)));

        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Healthy);
        static::assertFalse(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id', ['id' => $this->ids->getBytes('evt')]),
        );
        $this->assertEventLogStatus('evt', WebhookEventLogDefinition::STATUS_FAILED);
        static::assertSame(
            WebhookOutboxStore::CANCEL_REASON_HELD_EXPIRED,
            $this->connection->fetchOne('SELECT failure_reason FROM webhook_event_log WHERE id = :id', ['id' => $this->ids->getBytes('evt')]),
        );
    }

    private function instant(string $modifier): string
    {
        return $this->clock->now()->modify($modifier)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
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
            'created_at' => $this->instant('now'),
        ]);
    }

    private function seedHealth(
        string $key,
        EndpointState $state,
        int $ctf = 0,
        int $cnf = 0,
        int $cycle = 0,
        ?string $cooldownUntil = null,
        ?string $suspendedSince = null,
    ): void {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'consecutive_transient_failures' => $ctf,
            'consecutive_non_transient_failures' => $cnf,
            'degraded_cycle_count' => $cycle,
            'cooldown_until' => $cooldownUntil,
            'suspended_since' => $suspendedSince,
            'created_at' => $this->instant('now'),
        ]);
    }

    private function createHeldDelivery(string $eventKey, string $webhookKey, string $createdAt): void
    {
        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => WebhookEventLogDefinition::STATUS_PAUSED,
            'webhook_name' => 'test-hook',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => self::URL,
            'created_at' => $createdAt,
        ]);

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => $this->ids->getBytes($webhookKey),
            'partition_key' => Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128'),
            'delivery_status' => WebhookEventLogDefinition::STATUS_PAUSED,
            'execution_count' => 0,
            'created_at' => $createdAt,
        ]);
    }

    private function assertState(string $key, EndpointState $expected): void
    {
        static::assertSame($expected->value, $this->fetchHealthColumn($key, 'endpoint_state'));
    }

    private function assertDeliveryStatus(string $eventKey, string $expectedStatus): void
    {
        static::assertSame($expectedStatus, $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        ));
    }

    private function assertEventLogStatus(string $eventKey, string $expectedStatus): void
    {
        static::assertSame($expectedStatus, $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        ));
    }

    private function fetchHealthColumn(string $key, string $column): ?string
    {
        $value = $this->connection->fetchOne(
            \sprintf('SELECT %s FROM webhook_health WHERE webhook_id = :id', $column),
            ['id' => $this->ids->getBytes($key)]
        );

        return \is_string($value) ? $value : null;
    }

    private function fetchCycle(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT degraded_cycle_count FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }
}
