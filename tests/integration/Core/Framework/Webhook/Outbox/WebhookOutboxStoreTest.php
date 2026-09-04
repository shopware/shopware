<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Outbox;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
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
        $this->store->recordOutboxEntry($this->toEntry($message));

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

    public function testRecordHeldOutboxEntryWritesPausedRowVerbatim(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');

        $entry = $this->store->recordHeldOutboxEntry($this->toEntry($message));

        static::assertInstanceOf(OutboxEntry::class, $entry);
        static::assertSame(WebhookEventLogDefinition::STATUS_PAUSED, $entry->deliveryStatus);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_PAUSED, $delivery['delivery_status']);
        static::assertSame(0, (int) $delivery['execution_count']);

        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_PAUSED);
    }

    public function testResumeCancelsHeldRowsAgedPastGraceAndRedeliversYounger(): void
    {
        $this->createWebhook('wh-1');
        $oldInsert = $this->toEntry($this->createMessage('evt-old', 'wh-1'));
        static::assertNotNull($this->store->recordHeldOutboxEntry($oldInsert));
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($this->createMessage('evt-fresh', 'wh-1'))));

        $this->ageDeliveryRow('evt-old', '-25 hours');
        $this->ageDeliveryRow('evt-fresh', '-23 hours');

        $this->store->resumeDeliveriesForWebhook($this->ids->get('wh-1'));

        $this->assertDeliveryDeleted('evt-old');
        $oldLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, failure_reason, serialized_webhook_message FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-old')]
        );
        static::assertNotFalse($oldLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $oldLog['delivery_status']);
        static::assertSame(WebhookOutboxStore::CANCEL_REASON_HELD_EXPIRED, $oldLog['failure_reason']);
        static::assertSame($oldInsert->serializedMessage, $oldLog['serialized_webhook_message'], 'cancel must keep the payload — the row is the replay surface\'s input');

        $freshDelivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-fresh')]
        );
        static::assertNotFalse($freshDelivery);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $freshDelivery['delivery_status']);
        static::assertNotNull($freshDelivery['next_retry_at']);
        static::assertLessThanOrEqual(
            (new \DateTimeImmutable('+5 seconds'))->getTimestamp(),
            (new \DateTimeImmutable((string) $freshDelivery['next_retry_at']))->getTimestamp(),
            'a resumed row must be due immediately'
        );
        $this->assertEventLogStatus('evt-fresh', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
    }

    public function testReleaseOneTrialCancelsAgedOldestAndReleasesNextOldest(): void
    {
        $this->createWebhook('wh-1');
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($this->createMessage('evt-old', 'wh-1'))));
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($this->createMessage('evt-fresh', 'wh-1'))));

        $this->ageDeliveryRow('evt-old', '-25 hours');
        $this->ageDeliveryRow('evt-fresh', '-23 hours');

        $releasedId = $this->store->releaseOneTrial($this->ids->get('wh-1'));

        $this->assertDeliveryDeleted('evt-old');
        $oldLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, failure_reason FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-old')]
        );
        static::assertNotFalse($oldLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $oldLog['delivery_status']);
        static::assertSame(WebhookOutboxStore::CANCEL_REASON_HELD_EXPIRED, $oldLog['failure_reason']);

        $freshDelivery = $this->connection->fetchAssociative(
            'SELECT id, delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-fresh')]
        );
        static::assertNotFalse($freshDelivery);
        static::assertSame((int) $freshDelivery['id'], $releasedId, 'the trial must be the next-oldest deliverable row, not the cancelled one');
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $freshDelivery['delivery_status']);
        $this->assertEventLogStatus('evt-fresh', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
    }

    public function testResumedHeldBacklogDrainsInIdOrderAheadOfNewerTraffic(): void
    {
        $this->createWebhook('wh-1');
        $heldFirst = $this->createMessage('evt-held-1', 'wh-1');
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($heldFirst)));
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($this->createMessage('evt-held-2', 'wh-1'))));
        static::assertNotNull($this->store->recordOutboxEntry($this->toEntry($this->createMessage('evt-newer', 'wh-1'))));

        $this->store->resumeDeliveriesForWebhook($this->ids->get('wh-1'));

        $this->assertEventLogStatus('evt-held-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-held-2', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        $due = $this->store->fetchDue(
            Hasher::hashBinary($heldFirst->getPartitionKey(), 'xxh128'),
            [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY],
            10
        );

        static::assertSame(
            [$this->ids->get('evt-held-1'), $this->ids->get('evt-held-2'), $this->ids->get('evt-newer')],
            array_map(static fn (OutboxEntry $entry) => $entry->webhookEventId, $due),
            'the held backlog must drain in id order ahead of newer traffic'
        );
    }

    public function testPausedRowsAreInvisibleToUnmodifiedTransportUntilReleased(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($message)));
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($this->createMessage('evt-2', 'wh-1'))));

        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');
        $claimableStatuses = [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY];

        static::assertSame([], $this->store->fetchDue($partitionKey, $claimableStatuses, 10));
        static::assertNotContains(
            $partitionKey,
            $this->claimAllPartitions('worker-before-release', $claimableStatuses),
            'a partition holding only paused rows must not be claimable'
        );

        static::assertNotNull($this->store->releaseOneTrial($this->ids->get('wh-1')));

        $due = $this->store->fetchDue($partitionKey, $claimableStatuses, 10);
        static::assertCount(1, $due, 'exactly one row may be in flight after a single release');
        static::assertSame($this->ids->get('evt-1'), $due[0]->webhookEventId, 'the release must pick the oldest held row');
        static::assertContains(
            $partitionKey,
            $this->claimAllPartitions('worker-after-release', $claimableStatuses),
            'the released row must make the partition claimable again'
        );
    }

    public function testOrphanedHeldRowsAreExcludedFromStrandedFinderAndCancelledSeparately(): void
    {
        $this->createWebhook('wh-dead');
        $orphan = $this->toEntry($this->createMessage('evt-orphan', 'wh-dead'));
        static::assertNotNull($this->store->recordHeldOutboxEntry($orphan));

        $this->createWebhook('wh-live');
        $this->insertHealth('wh-live', EndpointState::Healthy);
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($this->createMessage('evt-live', 'wh-live'))));

        $this->connection->delete('webhook', ['id' => $this->ids->getBytes('wh-dead')]);
        static::assertNull(
            $this->connection->fetchOne(
                'SELECT webhook_id FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => $this->ids->getBytes('evt-orphan')]
            ),
            'the FK must null the orphaned delivery row',
        );

        $stranded = $this->store->findWebhookIdsWithStrandedHolds();
        static::assertNotContains(null, $stranded, 'an orphaned (webhook-deleted) row must not leak a NULL id into the finder');
        static::assertContains($this->ids->get('wh-live'), $stranded, 'the live healthy webhook is a genuine stranded hold');

        static::assertSame(1, $this->store->cancelOrphanedHeldRows(), 'exactly the one orphaned held row is cancelled');

        $this->assertDeliveryDeleted('evt-orphan');
        $orphanLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, failure_reason, serialized_webhook_message FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-orphan')]
        );
        static::assertNotFalse($orphanLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $orphanLog['delivery_status']);
        static::assertSame(WebhookOutboxStore::CANCEL_REASON_ORPHANED, $orphanLog['failure_reason']);
        static::assertSame($orphan->serializedMessage, $orphanLog['serialized_webhook_message'], 'cancel must keep the payload for replay');

        $this->assertDeliveryExists('evt-live');
        $this->assertEventLogStatus('evt-live', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertContains($this->ids->get('wh-live'), $this->store->findWebhookIdsWithStrandedHolds());
    }

    public function testCancelSurplusInFlightRowsNoOpsWhenWebhookRecoveredAfterSnapshot(): void
    {
        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Suspended);

        static::assertNotNull($this->store->recordOutboxEntry($this->toEntry($this->createMessage('evt-queued', 'wh-1'))));
        static::assertNotNull($this->store->recordOutboxEntry($this->toEntry($this->createMessage('evt-pending', 'wh-1'))));
        $running = $this->store->markRunning($this->ids->get('evt-pending'));
        static::assertNotNull($running);
        $this->store->markPendingRetry($running, new \DateTimeImmutable('+1 hour'), null);

        $this->connection->update(
            'webhook_health',
            ['endpoint_state' => EndpointState::Healthy->value],
            ['webhook_id' => $this->ids->getBytes('wh-1')]
        );

        static::assertSame(
            0,
            $this->store->cancelSurplusInFlightRows($this->ids->get('wh-1')),
            'a recovered webhook must not have its resumed backlog cancelled as surplus',
        );

        $this->assertDeliveryStatus('evt-queued', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->assertDeliveryStatus('evt-pending', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-queued', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->assertEventLogStatus('evt-pending', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
    }

    public function testCancelSurplusInFlightRowsCancelsSurplusOnGenuinelySuspendedWebhook(): void
    {
        $this->createWebhook('wh-1');
        $this->insertHealth('wh-1', EndpointState::Suspended);

        static::assertNotNull($this->store->recordOutboxEntry($this->toEntry($this->createMessage('evt-keep', 'wh-1'))));
        static::assertNotNull($this->store->recordOutboxEntry($this->toEntry($this->createMessage('evt-surplus', 'wh-1'))));

        static::assertSame(1, $this->store->cancelSurplusInFlightRows($this->ids->get('wh-1')));

        $this->assertDeliveryStatus('evt-keep', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->assertEventLogStatus('evt-keep', WebhookEventLogDefinition::STATUS_QUEUED);

        $this->assertDeliveryDeleted('evt-surplus');
        $surplusLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, failure_reason FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-surplus')]
        );
        static::assertNotFalse($surplusLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $surplusLog['delivery_status']);
        static::assertSame(WebhookOutboxStore::CANCEL_REASON_SUSPENDED, $surplusLog['failure_reason']);
    }

    public function testFindDisabledWebhookIdsWithHeldRowsReturnsOnlyDisabledWebhooksWithPausedRows(): void
    {
        $this->createWebhook('wh-disabled');
        $this->insertHealth('wh-disabled', EndpointState::Disabled);
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($this->createMessage('evt-disabled-held', 'wh-disabled'))));

        $this->createWebhook('wh-healthy');
        $this->insertHealth('wh-healthy', EndpointState::Healthy);
        static::assertNotNull($this->store->recordHeldOutboxEntry($this->toEntry($this->createMessage('evt-healthy-held', 'wh-healthy'))));

        $this->createWebhook('wh-disabled-claimable');
        $this->insertHealth('wh-disabled-claimable', EndpointState::Disabled);
        static::assertNotNull($this->store->recordOutboxEntry($this->toEntry($this->createMessage('evt-disabled-queued', 'wh-disabled-claimable'))));

        $ids = $this->store->findDisabledWebhookIdsWithHeldRows();

        static::assertContains($this->ids->get('wh-disabled'), $ids);
        static::assertNotContains($this->ids->get('wh-healthy'), $ids, 'a HEALTHY held row is a stranded hold, not this finder\'s concern');
        static::assertNotContains($this->ids->get('wh-disabled-claimable'), $ids, 'a DISABLED webhook with no paused row must not be returned');
    }

    public function testBackfillDeliveryCreatesDeliveryForQueuedEventLog(): void
    {
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

        $entry = $this->store->backfillDelivery($this->toEntry($message));

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
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function nonQueuedEventLogStatusProvider(): iterable
    {
        yield 'SUCCESS event_log is skipped' => [WebhookEventLogDefinition::STATUS_SUCCESS];
        yield 'RUNNING event_log is skipped' => [WebhookEventLogDefinition::STATUS_RUNNING];
        yield 'PENDING_RETRY event_log is skipped' => [WebhookEventLogDefinition::STATUS_PENDING_RETRY];
    }

    #[DataProvider('nonQueuedEventLogStatusProvider')]
    public function testBackfillDeliverySkipsWhenEventLogIsNotQueued(string $status): void
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

        static::assertNull($entry);

        $deliveryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(0, $deliveryCount);
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
        $this->store->recordOutboxEntry($entry);

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

        $first = $this->store->recordOutboxEntry($insert);
        static::assertInstanceOf(OutboxEntry::class, $first);

        $second = $this->store->recordOutboxEntry($insert);
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

        static::assertNull($this->store->recordOutboxEntry($insert));

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
        $this->store->recordOutboxEntry($this->toEntry($message));

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
        $this->store->recordOutboxEntry($this->toEntry($message2));
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
        $this->store->recordOutboxEntry($this->toEntry($message));

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
        $this->store->recordOutboxEntry($this->toEntry($message));

        $info = $this->store->markRunning($this->ids->get('evt-1'));

        static::assertInstanceOf(OutboxEntry::class, $info);
        static::assertSame(1, $info->executionCount);
        static::assertGreaterThan(0, $info->sequence);
    }

    public function testMarkRunningReturnsNullAfterSuccess(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

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
        $this->store->recordOutboxEntry($this->toEntry($message));

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
        $this->store->recordOutboxEntry($this->toEntry($message));

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

    /**
     * Mid-rolling-deploy: a trunk runner (no awareness of webhook_delivery) finalizes
     * event_log to SUCCESS/FAILED while a rework webhook_delivery row still sits in the
     * table. markRunning must refuse to claim it — otherwise the webhook fires again.
     */
    public function testMarkRunningIgnoresStrayDeliveryRowForTerminalEventLog(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

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

    public function testMarkSuccessOnStaleAttemptIsNoop(): void
    {
        $staleAttempt = $this->setUpStaleFirstAttempt();

        static::assertFalse($this->store->markSuccess($staleAttempt, null));
        $this->assertActiveSecondAttemptUntouched();
    }

    public function testMarkFailedOnStaleAttemptIsNoop(): void
    {
        $staleAttempt = $this->setUpStaleFirstAttempt();

        static::assertFalse($this->store->markFailed($staleAttempt, null));
        $this->assertActiveSecondAttemptUntouched();
    }

    public function testMarkPendingRetryOnStaleAttemptIsNoop(): void
    {
        $staleAttempt = $this->setUpStaleFirstAttempt();

        static::assertFalse($this->store->markPendingRetry($staleAttempt, new \DateTimeImmutable('+5 minutes'), null));
        $this->assertActiveSecondAttemptUntouched();
    }

    public function testResetForRetryOnStaleAttemptIsNoop(): void
    {
        $staleAttempt = $this->setUpStaleFirstAttempt();

        static::assertFalse($this->store->resetForRetry($staleAttempt, null));
        $this->assertActiveSecondAttemptUntouched();
    }

    public function testFinalMessengerRetryOnRunningDeliveryIsNoop(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

        static::assertNotNull($this->store->markRunning($this->ids->get('evt-1')));

        static::assertFalse($this->store->markFailedAfterRetryExhaustedIfIdle($this->ids->get('evt-1')));
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_RUNNING);
        $this->assertDeliveryExists('evt-1');
    }

    public function testFinalMessengerRetryOnFuturePendingRetryIsNoop(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        $this->store->markPendingRetry($entry, new \DateTimeImmutable('+5 minutes'), null);

        static::assertFalse($this->store->markFailedAfterRetryExhaustedIfIdle($this->ids->get('evt-1')));
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertDeliveryExists('evt-1');
    }

    public function testFinalMessengerRetryMarksFreshQueuedAsFailed(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

        static::assertTrue($this->store->markFailedAfterRetryExhaustedIfIdle($this->ids->get('evt-1')));
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_FAILED);
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testFinalMessengerRetryMarksDuePendingRetryAsFailed(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

        $entry = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        $this->store->markPendingRetry($entry, new \DateTimeImmutable('-5 minutes'), null);

        static::assertTrue($this->store->markFailedAfterRetryExhaustedIfIdle($this->ids->get('evt-1')));
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_FAILED);
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testFinalMessengerRetryIgnoresTerminalEventLog(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

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

    /**
     * Race:
     * - one worker wins markSuccess → event_log SUCCESS, delivery deleted
     * - a delivery row reappears (e.g. backfill, ops)
     * - another worker calls markFailed on it
     *
     * The row is removed, but event_log stays SUCCESS.
     */
    public function testMarkFailedDoesNotRollBackTerminalEventLog(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

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
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_SUCCESS);
        $this->assertDeliveryDeleted('evt-1');
    }

    public function testUndeliverableFetchedEntryMarksQueuedDeliveryFailed(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

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
        $this->store->recordOutboxEntry($this->toEntry($message));

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
        $this->store->recordOutboxEntry($this->toEntry($dueMessage));
        $this->store->recordOutboxEntry($this->toEntry($skippedMessage));

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

    public function testResetRunningForPartitionCancelsStaleRunningRowOnSuspendedWebhook(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));
        static::assertNotNull($this->store->markRunning($this->ids->get('evt-1')));

        $this->insertHealth('wh-1', EndpointState::Suspended);

        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');
        $this->store->resetRunningForPartition($partitionKey, 0);

        $this->assertDeliveryDeleted('evt-1');
        $log = $this->connection->fetchAssociative(
            'SELECT delivery_status, failure_reason, serialized_webhook_message IS NOT NULL AS has_payload
             FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($log);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $log['delivery_status']);
        static::assertSame(WebhookOutboxStore::CANCEL_REASON_SUSPENDED, $log['failure_reason']);
        static::assertSame(1, (int) $log['has_payload'], 'the cancelled leftover keeps its replay payload');

        static::assertSame([], $this->store->fetchDue($partitionKey, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY], 10));
    }

    public function testResetRunningForPartitionStillRequeuesCrashLeftoverOfNonSuspendedWebhook(): void
    {
        $this->createWebhook('wh-suspended');
        $this->insertHealth('wh-suspended', EndpointState::Suspended);
        $this->createWebhook('wh-healthy');

        $suspendedMessage = $this->createMessage('evt-suspended', 'wh-suspended');
        $this->store->recordOutboxEntry($this->toEntry($suspendedMessage));
        static::assertNotNull($this->store->markRunning($this->ids->get('evt-suspended')));
        $this->store->recordOutboxEntry($this->toEntry($this->createMessage('evt-healthy', 'wh-healthy')));
        static::assertNotNull($this->store->markRunning($this->ids->get('evt-healthy')));

        $this->store->resetRunningForPartition(Hasher::hashBinary($suspendedMessage->getPartitionKey(), 'xxh128'), 0);

        $this->assertDeliveryDeleted('evt-suspended');
        $this->assertEventLogStatus('evt-suspended', WebhookEventLogDefinition::STATUS_FAILED);

        $this->assertDeliveryStatus('evt-healthy', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-healthy', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
    }

    public function testResetRunningForPartitionOnTerminalEventLogIsNoop(): void
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

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

    public function testDeliveryWriteRestartsOrphanCleanupGracePeriod(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $store = new WebhookOutboxStore($this->connection, $clock);
        $streamLockService = new StreamLockService($this->connection, $clock);

        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $partitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');

        $this->connection->insert('webhook_stream', [
            'id' => Uuid::randomBytes(),
            'partition_key' => $partitionKey,
            'created_at' => $clock->now()
                ->modify(\sprintf('-%d seconds', StreamLockService::ORPHAN_GRACE_SECONDS + 1))
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        static::assertNotNull($store->recordOutboxEntry($this->toEntry($message)));

        // Remove the delivery so cleanup must rely on the refreshed grace window.
        $this->connection->executeStatement(
            'DELETE FROM webhook_delivery WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        $streamLockService->deleteOrphanedStreams(100);
        static::assertNotFalse(
            $this->connection->fetchOne('SELECT 1 FROM webhook_stream WHERE partition_key = :pk', ['pk' => $partitionKey]),
            'a delivery write must restart the stream cleanup grace period'
        );
    }

    /**
     * First attempt is claimed (RUNNING, execution_count=1), then `resetRunningForPartition`
     * recovers it (back to PENDING_RETRY), then a second attempt is claimed (RUNNING,
     * execution_count=2). The returned entry is the original first caller's claim — that
     * caller is no longer the owner: crash recovery handed the active attempt to a different
     * worker. Any terminal write the first caller tries must be rejected by `ownsRunningAttempt`.
     */
    private function setUpStaleFirstAttempt(): OutboxEntry
    {
        $this->createWebhook('wh-1');
        $message = $this->createMessage('evt-1', 'wh-1');
        $this->store->recordOutboxEntry($this->toEntry($message));

        $firstAttempt = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertInstanceOf(OutboxEntry::class, $firstAttempt);

        $this->store->resetRunningForPartition(Hasher::hashBinary($message->getPartitionKey(), 'xxh128'), 0);

        $secondAttempt = $this->store->markRunning($this->ids->get('evt-1'));
        static::assertInstanceOf(OutboxEntry::class, $secondAttempt);
        static::assertSame(2, $secondAttempt->executionCount);

        return $firstAttempt;
    }

    private function assertActiveSecondAttemptUntouched(): void
    {
        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status, execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($delivery, 'stale terminal call must not delete the active second attempt');
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $delivery['delivery_status']);
        static::assertSame(2, (int) $delivery['execution_count']);
        $this->assertEventLogStatus('evt-1', WebhookEventLogDefinition::STATUS_RUNNING);
    }

    private function ageDeliveryRow(string $eventKey, string $relativeTime): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET created_at = :createdAt WHERE webhook_event_log_id = :id',
            [
                'createdAt' => (new \DateTimeImmutable($relativeTime))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->getBytes($eventKey),
            ]
        );
    }

    /**
     * @param non-empty-list<WebhookEventLogDefinition::STATUS_QUEUED|WebhookEventLogDefinition::STATUS_PENDING_RETRY> $statuses
     *
     * @return list<string>
     */
    private function claimAllPartitions(string $workerId, array $statuses): array
    {
        $lockService = static::getContainer()->get(StreamLockService::class);

        $claimed = [];
        while (($lease = $lockService->claimNext($workerId, 60, $statuses)) !== null) {
            $claimed[] = $lease->partitionKey;
        }

        return $claimed;
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

    private function assertDeliveryStatus(string $eventKey, string $expectedStatus): void
    {
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertSame($expectedStatus, $status);
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

    private function insertHealth(string $webhookKey, EndpointState $state): void
    {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($webhookKey),
            'endpoint_state' => $state->value,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
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
