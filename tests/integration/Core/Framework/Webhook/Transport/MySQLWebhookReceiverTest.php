<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Transport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('framework')]
class MySQLWebhookReceiverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private WebhookOutboxStore $outbox;

    private StreamLockService $lockService;

    private MySQLWebhookReceiver $receiver;

    private MockClock $clock;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-04-20 10:00:00'));
        $this->outbox = new WebhookOutboxStore($this->connection, $this->clock);
        $this->lockService = new StreamLockService($this->connection, $this->clock);
        $this->receiver = new MySQLWebhookReceiver($this->lockService, $this->outbox, $this->clock, new NullLogger());
        $this->ids = new IdsCollection();
        $this->clearWebhookTables();
    }

    public function testGetClaimsPartitionAndYieldsEnvelope(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));

        static::assertCount(1, $envelopes);
        static::assertInstanceOf(Envelope::class, $envelopes[0]);
        $message = $envelopes[0]->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
        static::assertSame($this->ids->get('evt-1'), $message->getWebhookEventId());
    }

    public function testGetReturnsEmptyWhenNoDueDeliveries(): void
    {
        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));

        static::assertSame([], $envelopes);
    }

    public function testGetReleasesPartitionWhenFetchReturnsEmpty(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));
        $entry = $this->outbox->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        $this->outbox->markSuccess($entry, null);

        // Partition row still exists, but no due deliveries remain.
        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));

        static::assertSame([], $envelopes);

        $lockedCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_stream WHERE locked_by IS NOT NULL OR lock_expires_at IS NOT NULL'
        );
        static::assertSame(0, $lockedCount);
    }

    public function testYieldsEveryDueEntryInOneCallInInsertionOrder(): void
    {
        $this->createWebhook('wh-1');
        for ($i = 1; $i <= 3; ++$i) {
            $this->outbox->recordOutboxEntry($this->entryFor('evt-' . $i, 'wh-1'));
        }

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));

        static::assertCount(3, $envelopes);
        $ids = array_map(static function (Envelope $e): string {
            $msg = $e->getMessage();
            static::assertInstanceOf(WebhookEventMessage::class, $msg);

            return $msg->getWebhookEventId();
        }, $envelopes);
        static::assertSame(
            [$this->ids->get('evt-1'), $this->ids->get('evt-2'), $this->ids->get('evt-3')],
            $ids,
        );
    }

    /**
     * Crash recovery on partition claim:
     * - a RUNNING row is left behind by a crashed worker
     * - claimNext resets it to PENDING_RETRY (delivery + event_log mirror)
     * - only rows older than LEASE_SECONDS qualify, hence the backdated last_attempt_at
     */
    public function testResetsRunningRowsOnPartitionClaim(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-crashed', 'wh-1'));
        $this->outbox->markRunning($this->ids->get('evt-crashed'));
        $this->outbox->recordOutboxEntry($this->entryFor('evt-new', 'wh-1'));

        $staleAt = $this->clock->now()->modify(\sprintf('-%d seconds', MySQLWebhookReceiver::LEASE_SECONDS + 10));
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET last_attempt_at = :staleAt WHERE webhook_event_log_id = :id',
            [
                'staleAt' => $staleAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->getBytes('evt-crashed'),
            ]
        );

        iterator_to_array($this->asGenerator($this->receiver->get()));

        $crashed = $this->connection->fetchAssociative(
            'SELECT delivery_status, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-crashed')]
        );
        static::assertNotFalse($crashed);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $crashed['delivery_status']);
        static::assertNotNull($crashed['next_retry_at']);

        $eventLog = $this->connection->fetchAssociative(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-crashed')]
        );
        static::assertNotFalse($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $eventLog['delivery_status']);
    }

    /**
     * - stale RUNNING delivery row, event_log already SUCCESS
     * - partition has nothing live to claim
     * - claimNext skips it; SUCCESS stays untouched
     */
    public function testTerminalOnlyStaleRunningDeliveryDoesNotMakePartitionClaimable(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-terminal', 'wh-1'));

        $entry = $this->outbox->markRunning($this->ids->get('evt-terminal'));
        static::assertNotNull($entry);
        static::assertTrue($this->outbox->markSuccess($entry, null));

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes('evt-terminal'),
            'webhook_id' => $this->ids->getBytes('wh-1'),
            'partition_key' => $partitionKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_RUNNING,
            'execution_count' => 1,
            'last_attempt_at' => $this->clock->now()->modify(\sprintf('-%d seconds', MySQLWebhookReceiver::LEASE_SECONDS + 10))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $lease = $this->lockService->claimNext(
            'worker-1',
            MySQLWebhookReceiver::LEASE_SECONDS,
            [
                WebhookEventLogDefinition::STATUS_QUEUED,
                WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            ],
        );

        static::assertNull($lease);
        static::assertSame(
            WebhookEventLogDefinition::STATUS_SUCCESS,
            $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => $this->ids->getBytes('evt-terminal')]
            )
        );
    }

    public function testKeepaliveRenewsLease(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $envelopes);

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $expiresBefore = $this->connection->fetchOne(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        $this->clock->modify('+30 seconds');
        $this->receiver->keepalive($envelopes[0]);

        $expiresAfter = $this->connection->fetchOne(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        static::assertNotSame($expiresBefore, $expiresAfter);
    }

    /**
     * Stolen-lease recovery:
     * - the lock row is cleared out-of-band (lease lost to another worker)
     * - keepalive notices and drops the receiver's local lease state
     * - the next get() does a fresh claim and re-yields the still-QUEUED entry
     */
    public function testKeepaliveOnStolenLeaseDropsLeaseAndNextCallReclaims(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $first = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $first);

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $this->connection->executeStatement(
            'UPDATE webhook_stream SET locked_by = NULL, lock_expires_at = NULL WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        $this->receiver->keepalive($first[0]);

        $second = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $second);
        $secondMsg = $second[0]->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $secondMsg);
        static::assertSame($this->ids->get('evt-1'), $secondMsg->getWebhookEventId());
    }

    public function testReleasesLeaseOnReset(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        iterator_to_array($this->asGenerator($this->receiver->get()));

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        static::assertNotNull($this->connection->fetchOne(
            'SELECT locked_by FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        ));

        $this->receiver->reset();

        static::assertNull($this->connection->fetchOne(
            'SELECT locked_by FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        ));
    }

    /**
     * Messenger reject() on the webhook transport signals only an exceptional bubble out of
     * the handler — WebhookDeliveryService already owns retry persistence. The row stays
     * RUNNING and is recovered by resetRunningForPartition on the next claim.
     */
    public function testRejectLeavesRowForCrashRecovery(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Webhook handler rejected unexpectedly; leaving row for crash recovery',
                static::callback(
                    fn (array $context): bool => $context['webhookEventId'] === $this->ids->get('evt-1')
                        && $context['webhookId'] === $this->ids->get('wh-1')
                )
            );
        $receiver = new MySQLWebhookReceiver($this->lockService, $this->outbox, $this->clock, $logger);

        $envelopes = iterator_to_array($this->asGenerator($receiver->get()));
        static::assertCount(1, $envelopes);

        $this->outbox->markRunning($this->ids->get('evt-1'));
        $receiver->reject($envelopes[0]);

        $row = $this->connection->fetchAssociative(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($row);
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $row['delivery_status']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unprocessableBlobProvider(): iterable
    {
        yield 'wrong-class blob' => [serialize(new \stdClass())];
        yield 'malformed blob' => ['not-a-valid-serialized-message'];
    }

    #[DataProvider('unprocessableBlobProvider')]
    public function testUnprocessableBlobIsMarkedFailedAndPartitionContinues(string $serializedBlob): void
    {
        $this->createWebhook('wh-1');

        $eventLogId = Uuid::randomHex();
        $this->seedQueuedDelivery($eventLogId, 'wh-1', $serializedBlob);

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));

        static::assertSame([], $envelopes);
        $this->assertEntryFailedAndDeliveryDeleted($eventLogId);
    }

    /**
     * keepalive hint is a *minimum*, not an exact extension:
     * - Symfony's --keepalive sends small hints (default 5s) that must not collapse a healthy 240s lease
     * - a large hint (long-running handler signaling its own budget) must push expiry past the current value
     */
    public function testKeepaliveDoesNotShrinkLeaseForSmallHint(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $envelopes);

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $originalExpiry = $this->connection->fetchOne(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        $this->clock->modify('+1 seconds');
        $this->receiver->keepalive($envelopes[0], 5);

        $expiresAfter = $this->connection->fetchOne(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertSame($originalExpiry, $expiresAfter);
    }

    public function testKeepaliveExtendsLeaseForLargeHint(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $envelopes);

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $hintSeconds = MySQLWebhookReceiver::LEASE_SECONDS * 3;

        $this->clock->modify('+1 seconds');
        $this->receiver->keepalive($envelopes[0], $hintSeconds);

        $expected = $this->clock->now()
            ->modify(\sprintf('+%d seconds', $hintSeconds))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $expiresAfter = $this->connection->fetchOne(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertSame($expected, $expiresAfter);
    }

    public function testReusedLeaseStopsWhenOwnershipWasStolen(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $first = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $first);

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $this->connection->executeStatement(
            'UPDATE webhook_stream SET locked_by = :worker, lock_expires_at = :expiresAt WHERE partition_key = :pk',
            [
                'worker' => 'other-worker',
                'expiresAt' => $this->clock->now()->modify('+60 seconds')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'pk' => $partitionKey,
            ]
        );

        $this->outbox->recordOutboxEntry($this->entryFor('evt-2', 'wh-1'));

        $second = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertSame([], $second);
    }

    /**
     * Mid-batch lease expiry: another worker can claim the partition via SKIP LOCKED once
     * the DB lease lapses. The receiver's per-yield guard must stop yielding even though its
     * in-memory lease handle is still set.
     */
    public function testFetchStopsYieldingWhenLeaseExpiresMidBatch(): void
    {
        $this->createWebhook('wh-1');
        for ($i = 1; $i <= 3; ++$i) {
            $this->outbox->recordOutboxEntry($this->entryFor('evt-' . $i, 'wh-1'));
        }

        $yielded = [];
        foreach ($this->receiver->get() as $envelope) {
            $yielded[] = $envelope;
            if (\count($yielded) === 1) {
                $this->clock->modify(\sprintf('+%d seconds', MySQLWebhookReceiver::LEASE_SECONDS + 1));
            }
        }

        static::assertCount(1, $yielded);
    }

    /**
     * Fairness budget: a worker processing a hot partition must release after
     * MAX_MESSAGES_PER_LEASE deliveries so a different partition gets a turn. Without this,
     * a single-partition workload could hold the lease indefinitely.
     */
    public function testLeaseRotatesAfterMaxMessagesPerLeaseBudget(): void
    {
        $this->createWebhook('wh-1');
        $total = MySQLWebhookReceiver::MAX_MESSAGES_PER_LEASE + 2;
        for ($i = 1; $i <= $total; ++$i) {
            $this->outbox->recordOutboxEntry($this->entryFor('evt-' . $i, 'wh-1'));
        }

        $first = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(MySQLWebhookReceiver::MAX_MESSAGES_PER_LEASE, $first);

        foreach ($first as $envelope) {
            $message = $envelope->getMessage();
            static::assertInstanceOf(WebhookEventMessage::class, $message);
            $entry = $this->outbox->markRunning($message->getWebhookEventId());
            static::assertNotNull($entry);
            $this->outbox->markSuccess($entry, null);
        }

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $lockBetweenFetches = $this->connection->fetchOne(
            'SELECT locked_by FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertNotNull($lockBetweenFetches);

        $second = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(2, $second);
    }

    /**
     * - lease is held, but the rows we yielded are no longer due
     *   (handler scheduled retries or marked them done)
     * - fetchDue returns []; the receiver must release the lease
     */
    public function testFetchEmptyAfterClaimReleasesPartition(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->recordOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $first = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $first);

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $lockBefore = $this->connection->fetchAssociative(
            'SELECT locked_by, lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertNotFalse($lockBefore);
        static::assertNotNull($lockBefore['locked_by']);
        static::assertNotNull($lockBefore['lock_expires_at']);

        $farFuture = $this->clock->now()->modify('+1 hour')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET next_retry_at = :farFuture WHERE webhook_event_log_id = :id',
            [
                'farFuture' => $farFuture,
                'id' => $this->ids->getBytes('evt-1'),
            ]
        );

        $second = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertSame([], $second);

        $lockAfter = $this->connection->fetchAssociative(
            'SELECT locked_by, lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertNotFalse($lockAfter);
        static::assertNull($lockAfter['locked_by']);
        static::assertNull($lockAfter['lock_expires_at']);
    }

    /**
     * Row's webhook_event_log_id and the serialized blob's webhookEventId disagree
     * (botched migration / manual SQL splice). The receiver must fail the row by its DB id
     * rather than emit an envelope whose identity wouldn't match the tracked row.
     */
    public function testEventIdMismatchInBlobIsDropped(): void
    {
        $this->createWebhook('wh-1');

        $rowEventId = $this->ids->get('evt-row');
        $blobEventId = $this->ids->get('evt-blob');
        $blobMessage = new WebhookEventMessage(
            $blobEventId,
            ['body' => 'payload'],
            null,
            $this->ids->get('wh-1'),
            '6.7.0',
            'https://example.com/webhook',
            'test-secret',
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
            [],
            WebhookEventMessage::DEFAULT_PARTITION_KEY,
        );

        $this->seedQueuedDelivery($rowEventId, 'wh-1', serialize($blobMessage));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Discarded unreadable webhook delivery',
                static::callback(static fn (array $context): bool => $context['webhookEventId'] === $rowEventId)
            );
        $receiver = new MySQLWebhookReceiver($this->lockService, $this->outbox, $this->clock, $logger);

        $envelopes = iterator_to_array($this->asGenerator($receiver->get()));
        static::assertSame([], $envelopes);
        $this->assertEntryFailedAndDeliveryDeleted($rowEventId);
    }

    /**
     * Migration-corrupted blob: a NULL value in a non-nullable typed property makes unserialize
     * throw \TypeError. The receiver's generator is the worker boundary, so an uncaught throw
     * would kill the worker and stall the partition. Contract: drop the row, keep the worker.
     */
    public function testTypedPropertyMismatchBlobIsDropped(): void
    {
        $this->createWebhook('wh-1');

        $eventId = $this->ids->get('evt-type-mismatch');
        $validMessage = new WebhookEventMessage(
            $eventId,
            ['body' => 'payload'],
            null,
            $this->ids->get('wh-1'),
            '6.7.0',
            'https://example.com/webhook',
            'test-secret',
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
            [],
            WebhookEventMessage::DEFAULT_PARTITION_KEY,
        );
        $blob = serialize($validMessage);

        // Replace the url's serialized string with N; — typed-property check throws on unserialize.
        $urlValue = 'https://example.com/webhook';
        $needle = \sprintf('s:%d:"%s";', \strlen($urlValue), $urlValue);
        static::assertStringContainsString($needle, $blob, 'serialize output shape changed; test needs rework');
        $tamperedBlob = str_replace($needle, 'N;', $blob);

        $this->seedQueuedDelivery($eventId, 'wh-1', $tamperedBlob);

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertSame([], $envelopes);
        $this->assertEntryFailedAndDeliveryDeleted($eventId);
    }

    /**
     * @param iterable<Envelope> $iterable
     *
     * @return \Generator<int, Envelope>
     */
    private function asGenerator(iterable $iterable): \Generator
    {
        foreach ($iterable as $envelope) {
            yield $envelope;
        }
    }

    private function createWebhook(string $key): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => 'hook-' . $key,
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'app_id' => null,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * Inserts the three rows the receiver needs to fetch one delivery: webhook_event_log,
     * webhook_delivery, and webhook_stream. Caller controls the event_log id and the blob.
     */
    private function seedQueuedDelivery(string $eventLogIdHex, string $webhookKey, string $serializedBlob): void
    {
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');

        $this->connection->insert('webhook_event_log', [
            'id' => Uuid::fromHexToBytes($eventLogIdHex),
            'app_name' => null,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhook_name' => 'hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $now,
            'serialized_webhook_message' => $serializedBlob,
        ]);
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => Uuid::fromHexToBytes($eventLogIdHex),
            'webhook_id' => $this->ids->getBytes($webhookKey),
            'partition_key' => $partitionKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'created_at' => $now,
        ]);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            ['id' => Uuid::randomBytes(), 'pk' => $partitionKey, 'now' => $now]
        );
    }

    private function assertEntryFailedAndDeliveryDeleted(string $eventLogIdHex): void
    {
        static::assertSame(
            WebhookEventLogDefinition::STATUS_FAILED,
            $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($eventLogIdHex)]
            )
        );
        static::assertSame(
            0,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => Uuid::fromHexToBytes($eventLogIdHex)]
            )
        );
    }

    /**
     * setUp uses container-wired services that bypass IntegrationTestBehaviour's transaction,
     * so per-test row pollution has to be scrubbed explicitly.
     */
    private function clearWebhookTables(): void
    {
        $this->connection->executeStatement('DELETE FROM webhook_stream');
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook');
    }

    private function entryFor(string $eventKey, string $webhookKey): OutboxInsert
    {
        $message = new WebhookEventMessage(
            $this->ids->get($eventKey),
            ['body' => 'payload'],
            null,
            $this->ids->get($webhookKey),
            '6.7.0',
            'https://example.com/webhook',
            'test-secret',
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
            [],
            WebhookEventMessage::DEFAULT_PARTITION_KEY,
        );

        return new OutboxInsert(
            $message->getWebhookEventId(),
            $message->getWebhookId(),
            Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            serialize($message),
        );
    }
}
