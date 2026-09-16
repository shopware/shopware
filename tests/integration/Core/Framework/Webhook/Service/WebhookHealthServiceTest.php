<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class WebhookHealthServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private Connection $connection;

    private WebhookHealthService $service;

    private WebhookOutboxStore $outboxStore;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->service = static::getContainer()->get(WebhookHealthService::class);
        $this->outboxStore = static::getContainer()->get(WebhookOutboxStore::class);
    }

    public function testRecordTerminalFailureIncrementsBelowThreshold(): void
    {
        $this->insertWebhook('wh-1', errorCount: 0);

        $this->service->recordLegacyFailure($this->ids->get('wh-1'), WebhookFailureStrategy::DisableOnThreshold);

        static::assertSame(1, $this->fetchErrorCount('wh-1'));
        static::assertTrue($this->fetchActive('wh-1'));
    }

    public function testRecordTerminalFailureDeactivatesAtThreshold(): void
    {
        $this->insertWebhook('wh-1', errorCount: WebhookFailureStrategy::MAX_ERROR_COUNT - 1);

        $this->service->recordLegacyFailure($this->ids->get('wh-1'), WebhookFailureStrategy::DisableOnThreshold);

        static::assertSame(0, $this->fetchErrorCount('wh-1'));
        static::assertFalse($this->fetchActive('wh-1'));
    }

    public function testRecordTerminalFailureIsNoOpOnInactiveWebhook(): void
    {
        $this->insertWebhook('wh-1', errorCount: 3, active: false);

        $this->service->recordLegacyFailure($this->ids->get('wh-1'), WebhookFailureStrategy::DisableOnThreshold);

        static::assertSame(3, $this->fetchErrorCount('wh-1'));
        static::assertFalse($this->fetchActive('wh-1'));
    }

    public function testRecordTerminalFailureIsNoOpOnMissingWebhook(): void
    {
        $this->service->recordLegacyFailure(Uuid::randomHex(), WebhookFailureStrategy::DisableOnThreshold);

        $this->addToAssertionCount(1);
    }

    public function testRecordTerminalFailureKeepsActiveUnderIgnoreStrategy(): void
    {
        $this->insertWebhook('wh-1', errorCount: WebhookFailureStrategy::MAX_ERROR_COUNT + 5);

        $this->service->recordLegacyFailure($this->ids->get('wh-1'), WebhookFailureStrategy::Ignore);

        static::assertSame(WebhookFailureStrategy::MAX_ERROR_COUNT + 6, $this->fetchErrorCount('wh-1'));
        static::assertTrue($this->fetchActive('wh-1'));
    }

    public function testResetErrorCount(): void
    {
        $this->insertWebhook('wh-1', errorCount: 5);

        $this->service->resetErrorCount($this->ids->get('wh-1'));

        static::assertSame(0, $this->fetchErrorCount('wh-1'));
    }

    public function testRecordFailureOnAReclaimedAttemptWritesNoHealthEvidence(): void
    {
        $this->insertWebhook('wh-1');
        $this->seedHealth('wh-1', EndpointState::Healthy);
        $this->createDelivery('evt-1', 'wh-1');

        $entry = $this->outboxStore->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);

        $before = $this->fetchHealthRow('wh-1');

        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET execution_count = execution_count + 1 WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        $state = $this->service->recordFailure($this->ids->get('wh-1'), ErrorClassification::TransientServer, 1, $entry);

        static::assertSame($before, $this->fetchHealthRow('wh-1'));
        static::assertNull($state);
    }

    private function insertWebhook(string $key, int $errorCount = 0, bool $active = true): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'error_count' => $errorCount,
            'active' => (int) $active,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function seedHealth(string $key, EndpointState $state): void
    {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createDelivery(string $eventKey, string $webhookKey): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhook_name' => $webhookKey,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
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

    /**
     * @return array<string, mixed>
     */
    private function fetchHealthRow(string $key): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );

        static::assertIsArray($row);

        return $row;
    }

    private function fetchErrorCount(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT error_count FROM webhook WHERE id = :id',
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
}
