<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class EndpointHealthStateMachineMatrixTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const URL = 'https://endpoint.example.com/hook';

    private const DEGRADED_THRESHOLD = 5;
    private const COOLDOWN_TIER_0 = 300;
    private const COOLDOWN_TIER_2 = 1200;

    private IdsCollection $ids;

    private Connection $connection;

    private WebhookHealthService $service;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->service = static::getContainer()->get(WebhookHealthService::class);
    }

    public function testOnlyTheFailingWebhookForASharedEndpointDegradesAtTheFirstAttemptThreshold(): void
    {
        $this->seedWebhook('webhook-a', active: true, errorCount: 0);
        $this->seedWebhook('webhook-b', active: true, errorCount: 0);
        $this->seedHealth('webhook-a', EndpointState::Healthy, ctf: self::DEGRADED_THRESHOLD - 1);
        $this->seedHealth('webhook-b', EndpointState::Healthy, ctf: self::DEGRADED_THRESHOLD - 1);
        $this->createDelivery('a-queued', 'webhook-a', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->createDelivery('a-pending', 'webhook-a', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->createDelivery('b-queued', 'webhook-b', WebhookEventLogDefinition::STATUS_QUEUED);

        $retryResult = $this->service->recordFailure(
            $this->ids->get('webhook-a'),
            ErrorClassification::TransientNetwork,
            2,
        );

        static::assertSame(EndpointState::Healthy, $retryResult);
        static::assertSame(self::DEGRADED_THRESHOLD - 1, $this->fetchCtf('webhook-a'));
        $this->assertDeliveryStatus('a-queued', WebhookEventLogDefinition::STATUS_QUEUED);

        $result = $this->service->recordFailure(
            $this->ids->get('webhook-a'),
            ErrorClassification::TransientNetwork,
            1,
        );

        static::assertSame(EndpointState::Degraded, $result);
        $this->assertState('webhook-a', EndpointState::Degraded);
        static::assertSame(self::DEGRADED_THRESHOLD, $this->fetchCtf('webhook-a'));
        $this->assertHealthTimestampAbout('webhook-a', 'cooldown_until', self::COOLDOWN_TIER_0);
        $this->assertDeliveryStatus('a-queued', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertDeliveryStatus('a-pending', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertTrue($this->fetchActive('webhook-a'));
        static::assertSame(self::DEGRADED_THRESHOLD, $this->fetchErrorCount('webhook-a'));

        $this->assertState('webhook-b', EndpointState::Healthy);
        static::assertSame(self::DEGRADED_THRESHOLD - 1, $this->fetchCtf('webhook-b'));
        $this->assertDeliveryStatus('b-queued', WebhookEventLogDefinition::STATUS_QUEUED);
        static::assertTrue($this->fetchActive('webhook-b'));
        static::assertSame(0, $this->fetchErrorCount('webhook-b'));
    }

    public function testSuccessfulTrialRecoversTheWebhookAndResumesItsBacklog(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 5);
        $this->seedHealth(
            'wh',
            EndpointState::Degraded,
            ctf: 5,
            cnf: 1,
            cycle: 2,
            cooldownUntil: (new \DateTimeImmutable('+1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        );
        $this->createDelivery('evt-held', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(0, $this->fetchCtf('wh'));
        static::assertSame(0, $this->fetchCnf('wh'));
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertNull($this->fetchHealthColumn('wh', 'cooldown_until'));
        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        static::assertTrue($this->fetchActive('wh'));
        static::assertSame(0, $this->fetchErrorCount('wh'));
    }

    public function testOnlyFailuresAfterTheCooldownAdvanceTheDegradedLadder(): void
    {
        $futureCooldown = (new \DateTimeImmutable('+1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->seedWebhook('trial', active: true, errorCount: 5);
        $this->seedWebhook('straggler', active: true, errorCount: 5);
        $this->seedHealth('trial', EndpointState::Degraded, cycle: 1, cooldownUntil: self::past());
        $this->seedHealth('straggler', EndpointState::Degraded, cycle: 1, cooldownUntil: $futureCooldown);

        $this->service->recordFailure($this->ids->get('trial'), ErrorClassification::TransientServer, 1);
        $this->service->recordFailure($this->ids->get('straggler'), ErrorClassification::TransientServer, 1);

        static::assertSame(2, $this->fetchCycle('trial'));
        $this->assertHealthTimestampAbout('trial', 'cooldown_until', self::COOLDOWN_TIER_2);
        static::assertSame(1, $this->fetchCycle('straggler'));
        static::assertSame($futureCooldown, $this->fetchHealthColumn('straggler', 'cooldown_until'));
    }

    private static function past(): string
    {
        return (new \DateTimeImmutable('-1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
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
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function seedHealth(
        string $key,
        EndpointState $state,
        int $ctf = 0,
        int $cnf = 0,
        int $cycle = 0,
        ?string $cooldownUntil = null,
    ): void {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'consecutive_transient_failures' => $ctf,
            'consecutive_non_transient_failures' => $cnf,
            'degraded_cycle_count' => $cycle,
            'cooldown_until' => $cooldownUntil,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createDelivery(string $eventKey, string $webhookKey, string $deliveryStatus): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

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
        static::assertSame($expected->value, $this->fetchHealthColumn($key, 'endpoint_state'));
    }

    private function assertHealthTimestampAbout(string $key, string $column, int $secondsFromNow): void
    {
        $value = $this->fetchHealthColumn($key, $column);
        static::assertIsString($value, \sprintf('%s must be set', $column));
        static::assertEqualsWithDelta(
            (new \DateTimeImmutable())->getTimestamp() + $secondsFromNow,
            (new \DateTimeImmutable($value))->getTimestamp(),
            30,
            \sprintf('%s must be about %d seconds from now', $column, $secondsFromNow)
        );
    }

    private function fetchHealthColumn(string $key, string $column): ?string
    {
        $value = $this->connection->fetchOne(
            \sprintf('SELECT %s FROM webhook_health WHERE webhook_id = :id', $column),
            ['id' => $this->ids->getBytes($key)]
        );

        return \is_string($value) ? $value : null;
    }

    private function fetchCtf(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT consecutive_transient_failures FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchCnf(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT consecutive_non_transient_failures FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchCycle(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT degraded_cycle_count FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchActive(string $key): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT active FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchErrorCount(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT error_count FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function assertDeliveryStatus(string $eventKey, string $expectedStatus): void
    {
        $deliveryStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        $eventLogStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );

        static::assertSame($expectedStatus, $deliveryStatus, 'webhook_delivery status');
        static::assertSame($expectedStatus, $eventLogStatus, 'webhook_event_log status');
    }
}
