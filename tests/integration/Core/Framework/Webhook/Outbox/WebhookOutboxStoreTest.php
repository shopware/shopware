<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Outbox;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class WebhookOutboxStoreTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SAMPLE_PROCESSING_TIME_SECONDS = 42;

    private Connection $connection;

    private WebhookOutboxStore $store;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->store = static::getContainer()->get(WebhookOutboxStore::class);
        $this->ids = new IdsCollection();
    }

    public function testFullRetryCycle(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        // Attempt 1: QUEUED → RUNNING → fail → QUEUED (resetForRetry)
        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        $this->store->resetForRetry($entry, null);

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->assertDeliveryExists('evt-1');

        // Attempt 2: QUEUED → RUNNING → success → deleted
        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        $this->store->markSuccess($entry, null);

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_SUCCESS);
        $this->assertDeliveryDeleted('evt-1');
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function backfillDeliveryProvider(): iterable
    {
        yield 'QUEUED event_log gets a fresh delivery row' => [
            WebhookEventLogDefinition::STATUS_QUEUED,
            true,
        ];
        yield 'SUCCESS event_log is skipped' => [
            WebhookEventLogDefinition::STATUS_SUCCESS,
            false,
        ];
        yield 'RUNNING event_log is skipped' => [
            WebhookEventLogDefinition::STATUS_RUNNING,
            false,
        ];
        yield 'PENDING_RETRY event_log is skipped' => [
            WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            false,
        ];
    }

    #[DataProvider('backfillDeliveryProvider')]
    public function testBackfillDelivery(string $status, bool $expectEntry): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes('evt-1'),
            'delivery_status' => $status,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $entry = $this->store->backfillDelivery($this->toEntry($message));

        if ($expectEntry) {
            static::assertInstanceOf(OutboxEntry::class, $entry);

            $delivery = $this->connection->fetchAssociative(
                'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => $this->ids->getBytes('evt-1')]
            );
            static::assertNotFalse($delivery);
            static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $delivery['delivery_status']);

            $streamCount = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM webhook_stream WHERE partition_key = :pk',
                ['pk' => Hasher::hashBinary($message->getPartitionKey(), 'xxh128')]
            );
            static::assertSame(1, $streamCount);
        } else {
            static::assertNull($entry);
            $deliveryCount = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => $this->ids->getBytes('evt-1')]
            );
            static::assertSame(0, $deliveryCount);
        }
    }

    public function testBackfillDeliverySkipsWhenDeliveryAlreadyExists(): void
    {
        // Second backfill call is idempotent — first commit wins.
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes('evt-1'),
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $first = $this->store->backfillDelivery($this->toEntry($message));
        $second = $this->store->backfillDelivery($this->toEntry($message));

        static::assertInstanceOf(OutboxEntry::class, $first);
        static::assertNull($second);
    }

    public function testSerializedWebhookMessageIsStoredInEventLog(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $entry = $this->toEntry($message);
        $this->store->ensureOutboxEntry($entry);

        $stored = $this->connection->fetchOne(
            'SELECT serialized_webhook_message FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        static::assertNotFalse($stored);
        static::assertSame($entry->serializedMessage, $stored);
    }

    public function testEnsureOutboxEntryReturnsNullOnDuplicateInsert(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $insert = $this->toEntry($message);

        $first = $this->store->ensureOutboxEntry($insert);
        static::assertInstanceOf(OutboxEntry::class, $first);

        $second = $this->store->ensureOutboxEntry($insert);
        static::assertNull($second);

        $eventLogCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(1, $eventLogCount);

        $delivery = $this->connection->fetchAssociative(
            'SELECT id, execution_count, delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame($first->sequence, (int) $delivery['id']);
        static::assertSame($first->executionCount, (int) $delivery['execution_count']);
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $delivery['delivery_status']);
    }

    public function testEnsureOutboxEntryReturnsNullWhenWebhookMissing(): void
    {
        $insert = new OutboxInsert(
            webhookEventId: $this->ids->get('evt-1'),
            webhookId: Uuid::randomHex(),
            partitionKey: Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128'),
            serializedMessage: 'serialized-payload',
        );

        static::assertNull($this->store->ensureOutboxEntry($insert));

        $eventLogCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(0, $eventLogCount);

        $deliveryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(0, $deliveryCount);
    }

    public function testMarkPendingRetrySchedulesWithGivenRetryAt(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);

        $retryAt = new \DateTimeImmutable('+5 seconds');
        $this->store->markPendingRetry($entry, $retryAt, null);

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
        $this->store->ensureOutboxEntry($this->toEntry($message2));
        $entry2 = $this->store->markRunning($this->ids->get('evt-2'));
        static::assertNotNull($entry2);

        $retryAt2 = new \DateTimeImmutable('+30 seconds');
        $this->store->markPendingRetry($entry2, $retryAt2, null);

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
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);

        $retryAt = new \DateTimeImmutable('+5 seconds');
        $response = new DeliveryResponse(
            processingTimeSeconds: self::SAMPLE_PROCESSING_TIME_SECONDS,
            requestContent: json_encode(['headers' => []], \JSON_THROW_ON_ERROR),
            responseContent: json_encode(['body' => 'error'], \JSON_THROW_ON_ERROR),
            responseStatusCode: 500,
            responseReasonPhrase: 'Internal Server Error',
        );

        $this->store->markPendingRetry($entry, $retryAt, $response);

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
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $info = $this->store->markRunning($this->ids->get('evt-1'));

        static::assertInstanceOf(OutboxEntry::class, $info);
        static::assertSame(1, $info->executionCount);
        static::assertGreaterThan(0, $info->sequence);
    }

    public function testMarkRunningReturnsNullAfterSuccess(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        // Transition to RUNNING then to SUCCESS (delivery row deleted, event_log = SUCCESS)
        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        $this->store->markSuccess($entry, null);

        static::assertNull($this->store->markRunning($this->ids->get('evt-1')));
    }

    public function testMarkRunningReturnsNullOnSecondCall(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $first = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertInstanceOf(OutboxEntry::class, $first);
        static::assertSame(1, $first->executionCount);

        // Second concurrent call finds the row already RUNNING — caller must skip.
        static::assertNull($this->store->markRunning($this->ids->get('evt-1')));

        $count = (int) $this->connection->fetchOne(
            'SELECT execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(1, $count, 'execution_count must not increment on the no-op second call');
    }

    public function testMarkRunningTransitionsDuePendingRetryToRunning(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        // First attempt: QUEUED → RUNNING → PENDING_RETRY
        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        $this->store->markPendingRetry($entry, new \DateTimeImmutable('-5 minutes'), null);

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        // Second markRunning on a PENDING_RETRY row — must transition to RUNNING and increment count
        $result = $this->store->markRunning($this->ids->get('evt-1'));

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

    public function testMarkRunningSkipsPendingRetryUntilRetryAtIsDue(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        $retryAt = new \DateTimeImmutable('+5 minutes');
        $this->store->markPendingRetry($entry, $retryAt, null);

        static::assertNull($this->store->markRunning($this->ids->get('evt-1')));

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $delivery['delivery_status']);
        static::assertSame(1, (int) $delivery['execution_count']);
        static::assertSame($retryAt->format(Defaults::STORAGE_DATE_TIME_FORMAT), $delivery['next_retry_at']);

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
    }

    public function testMarkRunningIgnoresStrayDeliveryRowForTerminalEventLog(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertInstanceOf(OutboxEntry::class, $entry);
        static::assertTrue($this->store->markSuccess($entry, null));

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes('evt-1'),
            'webhook_id' => $this->ids->getBytes('wh-1'),
            'partition_key' => Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'execution_count' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        static::assertNull($this->store->markRunning($this->ids->get('evt-1')));

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_SUCCESS);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $delivery['delivery_status']);
        static::assertSame(0, (int) $delivery['execution_count']);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function staleAttemptTerminalProvider(): iterable
    {
        yield 'markSuccess' => ['markSuccess'];
        yield 'markFailed' => ['markFailed'];
        yield 'markPendingRetry' => ['markPendingRetry'];
        yield 'resetForRetry' => ['resetForRetry'];
    }

    #[DataProvider('staleAttemptTerminalProvider')]
    public function testStaleAttemptCannotMutateAfterCrashRecoveryStartsNextAttempt(string $terminal): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $firstAttempt = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertInstanceOf(OutboxEntry::class, $firstAttempt);

        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');
        $this->store->resetRunningForPartition($partitionKey, 0);

        $secondAttempt = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertInstanceOf(OutboxEntry::class, $secondAttempt);
        static::assertSame(2, $secondAttempt->executionCount);

        $changed = match ($terminal) {
            'markSuccess' => $this->store->markSuccess($firstAttempt, null),
            'markFailed' => $this->store->markFailed($firstAttempt, null),
            'markPendingRetry' => $this->store->markPendingRetry($firstAttempt, new \DateTimeImmutable('+5 minutes'), null),
            'resetForRetry' => $this->store->resetForRetry($firstAttempt, null),
            default => throw new \LogicException("Unknown terminal: {$terminal}"),
        };

        static::assertFalse($changed);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery, 'stale terminal call must not mutate the active second attempt');
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $delivery['delivery_status']);
        static::assertSame(2, (int) $delivery['execution_count']);
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_RUNNING);
    }

    public function testStaleAttemptWithMatchingExecutionCountButWrongSequenceLoses(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $attempt = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertInstanceOf(OutboxEntry::class, $attempt);

        $changed = $this->store->markSuccess(
            new OutboxEntry(
                webhookEventId: $attempt->webhookEventId,
                sequence: $attempt->sequence + 1,
                executionCount: $attempt->executionCount,
                deliveryStatus: $attempt->deliveryStatus,
            ),
            null,
        );

        static::assertFalse($changed);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $delivery['delivery_status']);
        static::assertSame($attempt->executionCount, (int) $delivery['execution_count']);
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_RUNNING);
    }

    /**
     * @return iterable<string, array{0: \Closure, 1: bool, 2: string, 3: bool}>
     */
    public static function finalMessengerRetryProvider(): iterable
    {
        yield 'RUNNING delivery is not clobbered' => [
            static function (self $test): void {
                $entry = $test->store->markRunning($test->ids->get('evt-1'));
                static::assertNotNull($entry);
            },
            false,
            WebhookEventLogDefinition::STATUS_RUNNING,
            true,
        ];

        yield 'future PENDING_RETRY is not clobbered' => [
            static function (self $test): void {
                $entry = $test->store->markRunning($test->ids->get('evt-1'));
                static::assertNotNull($entry);
                $test->store->markPendingRetry($entry, new \DateTimeImmutable('+5 minutes'), null);
            },
            false,
            WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            true,
        ];

        yield 'fresh QUEUED delivery is marked failed' => [
            static function (self $test): void {
                // No-op: ensureOutboxEntry already left the row QUEUED.
            },
            true,
            WebhookEventLogDefinition::STATUS_FAILED,
            false,
        ];

        yield 'due PENDING_RETRY delivery is marked failed' => [
            static function (self $test): void {
                $entry = $test->store->markRunning($test->ids->get('evt-1'));
                static::assertNotNull($entry);
                $test->store->markPendingRetry($entry, new \DateTimeImmutable('-5 minutes'), null);
            },
            true,
            WebhookEventLogDefinition::STATUS_FAILED,
            false,
        ];
    }

    #[DataProvider('finalMessengerRetryProvider')]
    public function testFinalMessengerRetry(\Closure $setup, bool $expectChanged, string $expectedEventLogStatus, bool $expectDelivery): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $setup($this);

        static::assertSame($expectChanged, $this->store->markFailedAfterRetryExhaustedIfIdle($this->ids->get('evt-1')));

        $this->assertEventLogStatus('evt-1', $expectedEventLogStatus);

        if ($expectDelivery) {
            $this->assertDeliveryExists('evt-1');
        } else {
            $this->assertDeliveryDeleted('evt-1');
        }
    }

    public function testFinalMessengerRetryIgnoresTerminalEventLog(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        static::assertTrue($this->store->markSuccess($entry, null));

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes('evt-1'),
            'webhook_id' => $this->ids->getBytes('wh-1'),
            'partition_key' => Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'execution_count' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        static::assertFalse($this->store->markFailedAfterRetryExhaustedIfIdle($this->ids->get('evt-1')));
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_SUCCESS);
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testMarkFailedCleansDanglingDeliveryForTerminalEventLogWhenFenceMatches(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        static::assertTrue($this->store->markSuccess($entry, null));

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes('evt-1'),
            'webhook_id' => $this->ids->getBytes('wh-1'),
            'partition_key' => Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            'delivery_status' => WebhookEventLogDefinition::STATUS_RUNNING,
            'execution_count' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $sequence = (int) $this->connection->lastInsertId();

        static::assertTrue($this->store->markFailed(
            new OutboxEntry(
                webhookEventId: $this->ids->get('evt-1'),
                sequence: $sequence,
                executionCount: 1,
                deliveryStatus: WebhookEventLogDefinition::STATUS_RUNNING,
            ),
            null,
        ));
        // event_log stays SUCCESS — updateEventLog's NOT IN (success, failed) guard prevents overwrite.
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_SUCCESS);
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testUndeliverableFetchedEntryMarksQueuedDeliveryFailed(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');
        $entries = $this->store->fetchDue($partitionKey, [WebhookEventLogDefinition::STATUS_QUEUED], 1);
        static::assertCount(1, $entries);

        static::assertTrue($this->store->markUndeliverableFetchedEntryFailed($entries[0]));

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_FAILED);
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testStaleUndeliverableFetchedEntryDoesNotDeleteActiveAttempt(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');
        $entries = $this->store->fetchDue($partitionKey, [WebhookEventLogDefinition::STATUS_QUEUED], 1);
        static::assertCount(1, $entries);

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);

        static::assertFalse($this->store->markUndeliverableFetchedEntryFailed($entries[0]));

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_RUNNING);
        $this->assertDeliveryExists('evt-1');
    }

    /**
     * @return iterable<string, array{0: \Closure}>
     */
    public static function fetchDueSkippedRowsProvider(): iterable
    {
        yield 'PENDING_RETRY with future next_retry_at' => [
            static function (Connection $connection, string $idBytes): void {
                $connection->executeStatement(
                    'UPDATE webhook_delivery SET delivery_status = :status, next_retry_at = :retry WHERE webhook_event_log_id = :id',
                    [
                        'status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                        'retry' => (new \DateTimeImmutable('+1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'id' => $idBytes,
                    ]
                );
            },
        ];
        yield 'RUNNING delivery row' => [
            static function (Connection $connection, string $idBytes): void {
                $connection->executeStatement(
                    'UPDATE webhook_delivery SET delivery_status = :s WHERE webhook_event_log_id = :id',
                    ['s' => WebhookEventLogDefinition::STATUS_RUNNING, 'id' => $idBytes]
                );
            },
        ];
    }

    #[DataProvider('fetchDueSkippedRowsProvider')]
    public function testFetchDueSkipsRowsThatAreNotDue(\Closure $setupSkipped): void
    {
        $this->createWebhook('wh-1');

        $dueMessage = $this->createMessage('evt-due', 'wh-1');
        $skippedMessage = $this->createMessage('evt-skipped', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($dueMessage));
        $this->store->ensureOutboxEntry($this->toEntry($skippedMessage));

        $setupSkipped($this->connection, $this->ids->getBytes('evt-skipped'));

        $partitionKey = Hasher::hashBinary($dueMessage->getPartitionKey(), 'xxh128');
        $results = $this->store->fetchDue(
            $partitionKey,
            [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY],
            10,
        );

        static::assertCount(1, $results);
        static::assertSame($dueMessage->getWebhookEventId(), $results[0]->webhookEventId);
    }

    public function testResetRunningForPartitionDoesNotResurrectTerminalEventLog(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->ensureOutboxEntry($this->toEntry($message));

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertInstanceOf(OutboxEntry::class, $entry);
        static::assertTrue($this->store->markSuccess($entry, null));

        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes('evt-1'),
            'webhook_id' => $this->ids->getBytes('wh-1'),
            'partition_key' => $partitionKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_RUNNING,
            'execution_count' => 1,
            'last_attempt_at' => (new \DateTimeImmutable('-5 minutes'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->store->resetRunningForPartition($partitionKey, 0);

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_SUCCESS);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $delivery['delivery_status']);

        static::assertSame([], $this->store->fetchDue($partitionKey, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY], 10));
        static::assertNull($this->store->markRunning($this->ids->get('evt-1')));
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
