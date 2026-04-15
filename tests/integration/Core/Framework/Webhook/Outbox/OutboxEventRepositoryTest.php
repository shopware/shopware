<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Outbox;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class OutboxEventRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private OutboxEventRepository $repository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->repository = static::getContainer()->get(OutboxEventRepository::class);
        $this->ids = new IdsCollection();
    }

    public function testEnsureOutboxEntryCreatesEventLogAndDeliveryRow(): void
    {
        $this->createWebhook('wh-1');

        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $eventLog = $this->connection->fetchAssociative(
            'SELECT * FROM `webhook_event_log` WHERE `id` = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        static::assertNotFalse($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $eventLog['delivery_status']);
        static::assertGreaterThan(0, (int) $eventLog['sequence']);

        $delivery = $this->connection->fetchAssociative(
            'SELECT * FROM `webhook_delivery` WHERE `webhook_event_log_id` = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $delivery['delivery_status']);
        static::assertSame(0, (int) $delivery['execution_count']);
    }

    public function testEnsureOutboxEntryIsIdempotent(): void
    {
        $this->createWebhook('wh-1');

        $message = $this->createMessage('evt-1', 'wh-1');

        $this->repository->ensureOutboxEntry($this->toEntry($message));
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `webhook_delivery` WHERE `webhook_event_log_id` = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        static::assertSame(1, $count);
    }

    public function testAppWebhookPartitionKeyUsesAppId(): void
    {
        $appId = $this->createApp('MyTestApp');
        $this->createWebhook('wh-1', $appId);

        $message = $this->createMessage('evt-1', 'wh-1', $appId);
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $partitionKey = $this->connection->fetchOne(
            'SELECT `partition_key` FROM `webhook_delivery` WHERE `webhook_event_log_id` = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        $expected = Hasher::hashBinary($appId, 'xxh128');

        static::assertSame($expected, $partitionKey);
    }

    public function testNonAppWebhookPartitionKey(): void
    {
        $this->createWebhook('wh-1');

        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $partitionKey = $this->connection->fetchOne(
            'SELECT `partition_key` FROM `webhook_delivery` WHERE `webhook_event_log_id` = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        $expected = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');

        static::assertSame($expected, $partitionKey);
    }

    public function testEventLogPopulatedFromWebhookTable(): void
    {
        $appId = $this->createApp('AuditApp');
        $this->createWebhook('wh-1', $appId);

        $message = $this->createMessage('evt-1', 'wh-1', $appId);
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $eventLog = $this->connection->fetchAssociative(
            'SELECT * FROM `webhook_event_log` WHERE `id` = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        static::assertNotFalse($eventLog);
        static::assertSame('AuditApp', $eventLog['app_name']);
        static::assertSame('test-hook', $eventLog['webhook_name']);
        static::assertSame('product.written', $eventLog['event_name']);
        static::assertSame('https://example.com/webhook', $eventLog['url']);
    }

    public function testDeliveryRowPopulatesWebhookId(): void
    {
        $this->createWebhook('wh-1');

        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $delivery = $this->connection->fetchAssociative(
            'SELECT * FROM `webhook_delivery` WHERE `webhook_event_log_id` = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        static::assertNotFalse($delivery);
        static::assertSame($this->ids->getBytes('wh-1'), $delivery['webhook_id']);
    }

    public function testMarkRunningTransitionsQueuedRow(): void
    {
        $this->createWebhook('wh-1');

        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));

        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $status);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $delivery['delivery_status']);
        static::assertSame(1, (int) $delivery['execution_count']);
    }

    public function testFullRetryCycle(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        // Attempt 1: QUEUED → RUNNING → fail → QUEUED (resetForRetry)
        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->resetForRetry($this->ids->get('evt-1'));

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->assertDeliveryExists('evt-1');

        // Attempt 2: QUEUED → RUNNING → success → deleted
        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->markSuccess($this->ids->get('evt-1'));

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_SUCCESS);
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testMarkSuccessDeletesDeliveryRow(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->markSuccess($this->ids->get('evt-1'), new DeliveryResponse(
            processingTime: 42,
            requestContent: json_encode(['headers' => []], \JSON_THROW_ON_ERROR),
            responseContent: json_encode(['body' => 'ok'], \JSON_THROW_ON_ERROR),
            responseStatusCode: 200,
            responseReasonPhrase: 'OK',
        ));

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_SUCCESS);
        $this->assertDeliveryDeleted('evt-1');

        $eventLog = $this->connection->fetchAssociative(
            'SELECT processing_time, response_status_code FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($eventLog);
        static::assertSame(42, (int) $eventLog['processing_time']);
        static::assertSame(200, (int) $eventLog['response_status_code']);
    }

    public function testMarkFailedDeletesDeliveryRow(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->markFailed($this->ids->get('evt-1'));

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_FAILED);
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testMarkPendingRetryKeepsDeliveryRow(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->resetForRetry($this->ids->get('evt-1'));

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->assertDeliveryExists('evt-1');

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $delivery['delivery_status']);
    }

    public function testExecutionCountIncrementsAcrossRetries(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        // Three attempts
        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->resetForRetry($this->ids->get('evt-1'));
        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->resetForRetry($this->ids->get('evt-1'));
        $this->repository->markRunning($this->ids->get('evt-1'));

        $count = (int) $this->connection->fetchOne(
            'SELECT execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(3, $count);
    }

    public function testMarkRunningUpdatesBothTablesAtomically(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));

        $eventLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, timestamp FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $eventLog['delivery_status']);
        static::assertNotNull($eventLog['timestamp']);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count, last_attempt_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $delivery['delivery_status']);
        static::assertSame(1, (int) $delivery['execution_count']);
        static::assertNotNull($delivery['last_attempt_at']);
    }

    public function testMarkSuccessDeletesDeliveryButKeepsEventLogWithResponse(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->markSuccess($this->ids->get('evt-1'), new DeliveryResponse(
            processingTime: 150,
            requestContent: json_encode(['headers' => ['X-Sw' => 'test']], \JSON_THROW_ON_ERROR),
            responseContent: json_encode(['body' => 'accepted'], \JSON_THROW_ON_ERROR),
            responseStatusCode: 200,
            responseReasonPhrase: 'OK',
        ));

        // Event log preserved with SUCCESS status and response data
        $eventLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, processing_time, request_content, response_content, response_status_code, response_reason_phrase FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $eventLog['delivery_status']);
        static::assertSame(150, (int) $eventLog['processing_time']);
        static::assertSame(200, (int) $eventLog['response_status_code']);
        static::assertSame('OK', $eventLog['response_reason_phrase']);
        static::assertNotNull($eventLog['request_content']);
        static::assertNotNull($eventLog['response_content']);

        // Delivery row deleted (hot queue eagerly cleaned)
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testMarkFailedDeletesDeliveryButKeepsEventLogWithResponse(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->markFailed($this->ids->get('evt-1'), new DeliveryResponse(
            processingTime: 300,
            requestContent: json_encode(['headers' => []], \JSON_THROW_ON_ERROR),
            responseContent: json_encode(['body' => 'error'], \JSON_THROW_ON_ERROR),
            responseStatusCode: 500,
            responseReasonPhrase: 'Internal Server Error',
        ));

        // Event log preserved with FAILED status and response data
        $eventLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, processing_time, response_status_code, response_reason_phrase FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $eventLog['delivery_status']);
        static::assertSame(300, (int) $eventLog['processing_time']);
        static::assertSame(500, (int) $eventLog['response_status_code']);
        static::assertSame('Internal Server Error', $eventLog['response_reason_phrase']);

        // Delivery row deleted (hot queue eagerly cleaned)
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testResetForRetryPreservesDeliveryRow(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->resetForRetry($this->ids->get('evt-1'), new DeliveryResponse(
            processingTime: 100,
            requestContent: '{}',
            responseStatusCode: 503,
            responseReasonPhrase: 'Service Unavailable',
        ));

        // Both tables back to QUEUED
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_QUEUED);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery, 'Delivery row must be preserved after resetForRetry');
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $delivery['delivery_status']);
        // execution_count stays at 1 from the markRunning call (resetForRetry does not change it)
        static::assertSame(1, (int) $delivery['execution_count']);

        // Event log has the response data from the failed attempt
        $eventLog = $this->connection->fetchAssociative(
            'SELECT processing_time, response_status_code FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($eventLog);
        static::assertSame(100, (int) $eventLog['processing_time']);
        static::assertSame(503, (int) $eventLog['response_status_code']);
    }

    public function testSequenceTrackingMatchesDeliveryId(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $eventLog = $this->connection->fetchAssociative(
            'SELECT sequence FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($eventLog);
        $sequence = (int) $eventLog['sequence'];
        static::assertGreaterThan(0, $sequence);

        $deliveryId = (int) $this->connection->fetchOne(
            'SELECT id FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertGreaterThan(0, $deliveryId);

        static::assertSame($deliveryId, $sequence, 'webhook_event_log.sequence must equal webhook_delivery.id (auto-increment)');
    }

    public function testEnsureOutboxEntryThrowsWebhookNotFoundForMissingWebhook(): void
    {
        $nonExistentWebhookId = Uuid::randomHex();
        $entry = new OutboxEntry(
            Uuid::randomHex(),
            $nonExistentWebhookId,
            Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128'),
            serialize('test'),
        );

        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage('Webhook "' . $nonExistentWebhookId . '" not found.');

        $this->repository->ensureOutboxEntry($entry);
    }

    public function testSerializedWebhookMessageIsStoredInEventLog(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $entry = $this->toEntry($message);
        $this->repository->ensureOutboxEntry($entry);

        $stored = $this->connection->fetchOne(
            'SELECT serialized_webhook_message FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        static::assertNotFalse($stored);
        static::assertSame($entry->serializedMessage, $stored);
    }

    private function assertEventLogStatus(string $eventKey, string $expectedStatus): void
    {
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertSame($expectedStatus, $status);
    }

    private function assertDeliveryExists(string $eventKey): void
    {
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertNotFalse($exists, 'Expected delivery row to exist');
    }

    private function assertDeliveryDeleted(string $eventKey): void
    {
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertFalse($exists, 'Expected delivery row to be deleted');
    }

    private function createApp(string $name = 'TestApp'): string
    {
        $appId = Uuid::randomHex();
        $appRepository = static::getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => $name,
            'active' => true,
            'path' => __DIR__,
            'version' => '1.0.0',
            'label' => 'test',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test-' . $appId,
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'test-role-' . $appId,
            ],
        ]], Context::createDefaultContext());

        return $appId;
    }

    private function createWebhook(string $webhookKey, ?string $appId = null): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($webhookKey),
            'name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'app_id' => $appId !== null ? Uuid::fromHexToBytes($appId) : null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createMessage(
        string $eventKey,
        string $webhookKey,
        ?string $appId = null,
    ): WebhookEventMessage {
        return new WebhookEventMessage(
            $this->ids->get($eventKey),
            ['body' => 'payload'],
            $appId,
            $this->ids->get($webhookKey),
            '6.7.0',
            'https://example.com/webhook',
            'test-secret',
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
            [],
            $appId ?? WebhookEventMessage::DEFAULT_PARTITION_KEY,
        );
    }

    private function toEntry(WebhookEventMessage $message): OutboxEntry
    {
        return new OutboxEntry(
            $message->getWebhookEventId(),
            $message->getWebhookId(),
            Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            serialize($message),
        );
    }
}
