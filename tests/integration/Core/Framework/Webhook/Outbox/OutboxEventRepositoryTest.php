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
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class OutboxEventRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SAMPLE_PROCESSING_TIME_SECONDS = 42;

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
            processingTimeSeconds: self::SAMPLE_PROCESSING_TIME_SECONDS,
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
        static::assertSame(self::SAMPLE_PROCESSING_TIME_SECONDS, (int) $eventLog['processing_time']);
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
            processingTimeSeconds: self::SAMPLE_PROCESSING_TIME_SECONDS,
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
        static::assertSame(self::SAMPLE_PROCESSING_TIME_SECONDS, (int) $eventLog['processing_time']);
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
            processingTimeSeconds: self::SAMPLE_PROCESSING_TIME_SECONDS,
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
        static::assertSame(self::SAMPLE_PROCESSING_TIME_SECONDS, (int) $eventLog['processing_time']);
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
            processingTimeSeconds: self::SAMPLE_PROCESSING_TIME_SECONDS,
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
        static::assertSame(self::SAMPLE_PROCESSING_TIME_SECONDS, (int) $eventLog['processing_time']);
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

    public function testEnsureOutboxEntryIsNoOpForMissingWebhook(): void
    {
        $nonExistentWebhookId = Uuid::randomHex();
        $eventLogId = Uuid::randomHex();
        $entry = new OutboxInsert(
            $eventLogId,
            $nonExistentWebhookId,
            Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128'),
            serialize('test'),
        );

        // Deleted webhook: insertEventLog returns false (SELECT finds no webhook row).
        // ensureOutboxEntry must silently do nothing — no exception, no rows written.
        $this->repository->ensureOutboxEntry($entry);

        $rowCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($eventLogId)]
        );
        static::assertSame(0, $rowCount, 'No event_log row should be created when webhook does not exist');
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

    public function testMarkPendingRetrySchedulesWithGivenRetryAt(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));

        $retryAt = new \DateTimeImmutable('+5 seconds');
        $this->repository->markPendingRetry($this->ids->get('evt-1'), $retryAt);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $delivery['delivery_status']);

        $nextRetryAt = new \DateTimeImmutable($delivery['next_retry_at']);
        static::assertEqualsWithDelta($retryAt->getTimestamp(), $nextRetryAt->getTimestamp(), 2);

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        // Test with a different retry time
        $this->createWebhook('wh-2');
        $message2 = $this->createMessage('evt-2', 'wh-2');
        $this->repository->ensureOutboxEntry($this->toEntry($message2));
        $this->repository->markRunning($this->ids->get('evt-2'));

        $retryAt2 = new \DateTimeImmutable('+30 seconds');
        $this->repository->markPendingRetry($this->ids->get('evt-2'), $retryAt2);

        $delivery2 = $this->connection->fetchAssociative(
            'SELECT next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-2')]
        );
        static::assertNotFalse($delivery2);
        $nextRetryAt2 = new \DateTimeImmutable($delivery2['next_retry_at']);
        static::assertEqualsWithDelta($retryAt2->getTimestamp(), $nextRetryAt2->getTimestamp(), 2);
    }

    public function testMarkPendingRetryPersistsResponseData(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));

        $retryAt = new \DateTimeImmutable('+5 seconds');
        $response = new DeliveryResponse(
            processingTimeSeconds: self::SAMPLE_PROCESSING_TIME_SECONDS,
            requestContent: json_encode(['headers' => []], \JSON_THROW_ON_ERROR),
            responseContent: json_encode(['body' => 'error'], \JSON_THROW_ON_ERROR),
            responseStatusCode: 500,
            responseReasonPhrase: 'Internal Server Error',
        );

        $this->repository->markPendingRetry($this->ids->get('evt-1'), $retryAt, $response);

        $eventLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, processing_time, response_status_code FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $eventLog['delivery_status']);
        static::assertSame(self::SAMPLE_PROCESSING_TIME_SECONDS, (int) $eventLog['processing_time']);
        static::assertSame(500, (int) $eventLog['response_status_code']);
    }

    public function testMarkRunningReturnsExecutionInfo(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $info = $this->repository->markRunning($this->ids->get('evt-1'));

        static::assertInstanceOf(OutboxEntry::class, $info);
        static::assertSame(1, $info->executionCount);
        static::assertGreaterThan(0, $info->sequence);
    }

    public function testMarkRunningReturnsNullAfterSuccess(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        // Transition to RUNNING then to SUCCESS (delivery row deleted, event_log = SUCCESS)
        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->markSuccess($this->ids->get('evt-1'));

        static::assertNull($this->repository->markRunning($this->ids->get('evt-1')));
    }

    public function testMarkRunningReturnsNullOnSecondCall(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $first = $this->repository->markRunning($this->ids->get('evt-1'));
        static::assertInstanceOf(OutboxEntry::class, $first);
        static::assertSame(1, $first->executionCount);

        // Second concurrent call finds the row already RUNNING — caller must skip.
        static::assertNull($this->repository->markRunning($this->ids->get('evt-1')));

        $count = (int) $this->connection->fetchOne(
            'SELECT execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(1, $count, 'execution_count must not increment on the no-op second call');
    }

    public function testMarkRunningTransitionsPendingRetryToRunning(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        // First attempt: QUEUED → RUNNING → PENDING_RETRY
        $this->repository->markRunning($this->ids->get('evt-1'));
        $this->repository->markPendingRetry($this->ids->get('evt-1'), new \DateTimeImmutable('+5 minutes'));

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        // Second markRunning on a PENDING_RETRY row — must transition to RUNNING and increment count
        $result = $this->repository->markRunning($this->ids->get('evt-1'));

        static::assertInstanceOf(OutboxEntry::class, $result);
        static::assertSame(2, $result->executionCount, 'execution_count must be incremented from 1 to 2');

        // delivery row must now be RUNNING
        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $delivery['delivery_status']);
        static::assertSame(2, (int) $delivery['execution_count']);

        // event_log must also be RUNNING
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_RUNNING);
    }

    public function testMarkRunningReturnsNullForUnknownEventLogId(): void
    {
        static::assertNull($this->repository->markRunning(Uuid::randomHex()));
    }

    public function testEnsureOutboxEntryCreatesStreamRow(): void
    {
        $this->createWebhook('wh-1');

        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');
        $exists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertTrue($exists);
    }

    public function testEnsureOutboxEntryIsIdempotentOnStreamRow(): void
    {
        $this->createWebhook('wh-1');
        $this->createWebhook('wh-2');

        $messageA = $this->createMessage('evt-1', 'wh-1');
        $messageB = $this->createMessage('evt-2', 'wh-2');

        $this->repository->ensureOutboxEntry($this->toEntry($messageA));
        $this->repository->ensureOutboxEntry($this->toEntry($messageB));

        $partitionKey = Hasher::hashBinary($messageA->getPartitionKey(), 'xxh128');
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertSame(1, $count, 'Two deliveries on the same partition must share a single stream row');
    }

    public function testFetchDueSkipsPendingRetryInFuture(): void
    {
        $this->createWebhook('wh-1');

        $dueMessage = $this->createMessage('evt-due', 'wh-1');
        $futureMessage = $this->createMessage('evt-future', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($dueMessage));
        $this->repository->ensureOutboxEntry($this->toEntry($futureMessage));

        // Make one of them pending_retry with a future next_retry_at
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET delivery_status = :status, next_retry_at = :retry WHERE webhook_event_log_id = :id',
            [
                'status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                'retry' => (new \DateTimeImmutable('+1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->getBytes('evt-future'),
            ]
        );

        $partitionKey = Hasher::hashBinary($dueMessage->getPartitionKey(), 'xxh128');
        $results = $this->repository->fetchDue($partitionKey, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY], 10);

        static::assertCount(1, $results);
        static::assertSame($dueMessage->getWebhookEventId(), $results[0]->webhookEventId);
    }

    public function testFetchDueReturnsSerializedMessagePayload(): void
    {
        $this->createWebhook('wh-1');

        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');
        $results = $this->repository->fetchDue($partitionKey, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY], 10);

        static::assertCount(1, $results);
        static::assertSame(serialize($message), $results[0]->serializedWebhookMessage, 'Stored payload must be a faithful serialize() of the original message');
    }

    public function testFetchDueSkipsOtherPartitions(): void
    {
        $this->createWebhook('wh-1');
        $appId = $this->createApp('AppOther');
        $this->createWebhook('wh-2', $appId);

        $messageA = $this->createMessage('evt-a', 'wh-1');
        $messageB = $this->createMessage('evt-b', 'wh-2', $appId);
        $this->repository->ensureOutboxEntry($this->toEntry($messageA));
        $this->repository->ensureOutboxEntry($this->toEntry($messageB));

        $partitionKeyA = Hasher::hashBinary($messageA->getPartitionKey(), 'xxh128');
        $results = $this->repository->fetchDue($partitionKeyA, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY], 10);

        static::assertCount(1, $results);
        static::assertSame($messageA->getWebhookEventId(), $results[0]->webhookEventId);
    }

    public function testFetchDueSkipsRunningAndTerminalStatuses(): void
    {
        $this->createWebhook('wh-fd-queued');
        $this->createWebhook('wh-fd-running');

        $queuedMsg = $this->createMessage('evt-fd-queued', 'wh-fd-queued');
        $runningMsg = $this->createMessage('evt-fd-running', 'wh-fd-running');
        $this->repository->ensureOutboxEntry($this->toEntry($queuedMsg));
        $this->repository->ensureOutboxEntry($this->toEntry($runningMsg));

        // Flip one row to RUNNING
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET delivery_status = :s WHERE webhook_event_log_id = :id',
            ['s' => WebhookEventLogDefinition::STATUS_RUNNING, 'id' => $this->ids->getBytes('evt-fd-running')]
        );

        $partitionKey = Hasher::hashBinary($queuedMsg->getPartitionKey(), 'xxh128');
        $results = $this->repository->fetchDue($partitionKey, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY], 10);

        static::assertCount(1, $results);
        static::assertSame($queuedMsg->getWebhookEventId(), $results[0]->webhookEventId);
    }

    public function testFetchDueReturnsStatusOnPendingDelivery(): void
    {
        $this->createWebhook('wh-ds-1');
        $this->createWebhook('wh-ds-2');

        $queuedMsg = $this->createMessage('evt-ds-queued', 'wh-ds-1');
        $pendingMsg = $this->createMessage('evt-ds-pending', 'wh-ds-2');
        $this->repository->ensureOutboxEntry($this->toEntry($queuedMsg));
        $this->repository->ensureOutboxEntry($this->toEntry($pendingMsg));

        // Flip one row to PENDING_RETRY with a past next_retry_at so it is due
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET delivery_status = :s, next_retry_at = :t WHERE webhook_event_log_id = :id',
            [
                's' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                't' => (new \DateTimeImmutable('-1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->getBytes('evt-ds-pending'),
            ]
        );

        $partitionKey = Hasher::hashBinary($queuedMsg->getPartitionKey(), 'xxh128');
        $results = $this->repository->fetchDue($partitionKey, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY], 10);

        static::assertCount(2, $results);

        $byEvent = [];
        foreach ($results as $r) {
            $byEvent[$r->webhookEventId] = $r->deliveryStatus;
        }

        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $byEvent[$queuedMsg->getWebhookEventId()]);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $byEvent[$pendingMsg->getWebhookEventId()]);
    }

    public function testFetchDueFiltersByStatusList(): void
    {
        $this->createWebhook('wh-sf-1');
        $this->createWebhook('wh-sf-2');

        $queuedMsg = $this->createMessage('evt-sf-queued', 'wh-sf-1');
        $pendingMsg = $this->createMessage('evt-sf-pending', 'wh-sf-2');
        $this->repository->ensureOutboxEntry($this->toEntry($queuedMsg));
        $this->repository->ensureOutboxEntry($this->toEntry($pendingMsg));

        // Flip one row to PENDING_RETRY with a past next_retry_at so it is due
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET delivery_status = :s, next_retry_at = :t WHERE webhook_event_log_id = :id',
            [
                's' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                't' => (new \DateTimeImmutable('-1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->getBytes('evt-sf-pending'),
            ]
        );

        $partitionKey = Hasher::hashBinary($queuedMsg->getPartitionKey(), 'xxh128');
        $results = $this->repository->fetchDue($partitionKey, [WebhookEventLogDefinition::STATUS_PENDING_RETRY], 10);

        static::assertCount(1, $results);
        static::assertSame($pendingMsg->getWebhookEventId(), $results[0]->webhookEventId);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $results[0]->deliveryStatus);
    }

    public function testResetRunningForPartitionResetsBothTables(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->repository->ensureOutboxEntry($this->toEntry($message));

        $this->repository->markRunning($this->ids->get('evt-1'));

        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');
        $this->repository->resetRunningForPartition($partitionKey, 0);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $delivery['delivery_status']);
        static::assertNotNull($delivery['next_retry_at']);

        $eventLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, timestamp FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $eventLog['delivery_status']);
        static::assertGreaterThan(0, (int) $eventLog['timestamp']);
    }

    public function testResetRunningForPartitionIsPartitionScoped(): void
    {
        $this->createWebhook('wh-a');
        $appId = $this->createApp('OtherApp');
        $this->createWebhook('wh-b', $appId);

        $messageA = $this->createMessage('evt-a', 'wh-a');
        $messageB = $this->createMessage('evt-b', 'wh-b', $appId);
        $this->repository->ensureOutboxEntry($this->toEntry($messageA));
        $this->repository->ensureOutboxEntry($this->toEntry($messageB));

        $this->repository->markRunning($this->ids->get('evt-a'));
        $this->repository->markRunning($this->ids->get('evt-b'));

        // Only reset partition A
        $partitionKeyA = Hasher::hashBinary($messageA->getPartitionKey(), 'xxh128');
        $this->repository->resetRunningForPartition($partitionKeyA, 0);

        $statusA = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-a')]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $statusA);

        $statusB = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-b')]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $statusB);
    }

    public function testResetRunningForPartitionIgnoresNonRunningRows(): void
    {
        $this->createWebhook('wh-1');
        $this->createWebhook('wh-2');
        $this->createWebhook('wh-3');

        $queued = $this->createMessage('evt-q', 'wh-1');
        $running = $this->createMessage('evt-r', 'wh-2');
        $pending = $this->createMessage('evt-p', 'wh-3');

        $this->repository->ensureOutboxEntry($this->toEntry($queued));
        $this->repository->ensureOutboxEntry($this->toEntry($running));
        $this->repository->ensureOutboxEntry($this->toEntry($pending));

        $this->repository->markRunning($this->ids->get('evt-r'));
        $this->repository->markRunning($this->ids->get('evt-p'));
        $this->repository->markPendingRetry($this->ids->get('evt-p'), new \DateTimeImmutable('+10 minutes'));

        $partitionKey = Hasher::hashBinary($queued->getPartitionKey(), 'xxh128');
        $this->repository->resetRunningForPartition($partitionKey, 0);

        // QUEUED row untouched
        static::assertSame(
            WebhookEventLogDefinition::STATUS_QUEUED,
            $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => $this->ids->getBytes('evt-q')]
            )
        );
        // RUNNING → PENDING_RETRY
        static::assertSame(
            WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => $this->ids->getBytes('evt-r')]
            )
        );
        // PENDING_RETRY row: status unchanged, future next_retry_at preserved
        $pendingRow = $this->connection->fetchAssociative(
            'SELECT delivery_status, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-p')]
        );
        static::assertNotFalse($pendingRow);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $pendingRow['delivery_status']);
        static::assertStringContainsString(
            (new \DateTimeImmutable('+10 minutes'))->format('Y-m-d'),
            (string) $pendingRow['next_retry_at']
        );
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

    private function toEntry(WebhookEventMessage $message): OutboxInsert
    {
        return new OutboxInsert(
            $message->getWebhookEventId(),
            $message->getWebhookId(),
            Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            serialize($message),
        );
    }
}
