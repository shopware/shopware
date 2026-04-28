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
        $this->truncateHarnessTables();
    }

    public function testGetClaimsPartitionAndYieldsEnvelope(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));

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
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));
        $entry = $this->outbox->markRunning($this->ids->get('evt-1'));
        static::assertNotNull($entry);
        $this->outbox->markSuccess($entry, null);

        // Partition row still exists, but no due deliveries remain.
        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));

        static::assertSame([], $envelopes);
    }

    public function testYieldsEveryDueEntryInOneCallInInsertionOrder(): void
    {
        $this->createWebhook('wh-1');
        for ($i = 1; $i <= 3; ++$i) {
            $this->outbox->ensureOutboxEntry($this->entryFor('evt-' . $i, 'wh-1'));
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

    public function testResetsRunningRowsOnPartitionClaim(): void
    {
        $this->createWebhook('wh-1');

        // Crashed worker left a RUNNING row; a new message arrived afterwards so the
        // partition is claimable.
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-crashed', 'wh-1'));
        $this->outbox->markRunning($this->ids->get('evt-crashed'));
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-new', 'wh-1'));

        // A fresh markRunning wrote `last_attempt_at = now` (MockClock); recovery only
        // touches rows older than LEASE_SECONDS. Backdate relative to the mock clock so
        // the crashed row qualifies as stale.
        $staleAt = $this->clock->now()->modify(\sprintf('-%d seconds', MySQLWebhookReceiver::LEASE_SECONDS + 10));
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET last_attempt_at = :staleAt WHERE webhook_event_log_id = :id',
            [
                'staleAt' => $staleAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->getBytes('evt-crashed'),
            ]
        );

        iterator_to_array($this->asGenerator($this->receiver->get()));

        // Crashed RUNNING row has been reset to PENDING_RETRY with next_retry_at = now.
        $crashed = $this->connection->fetchAssociative(
            'SELECT delivery_status, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-crashed')]
        );
        static::assertNotFalse($crashed);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $crashed['delivery_status']);
        static::assertNotNull($crashed['next_retry_at']);

        // event_log mirror was also updated.
        $eventLog = $this->connection->fetchAssociative(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-crashed')]
        );
        static::assertNotFalse($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $eventLog['delivery_status']);
    }

    public function testTerminalOnlyStaleRunningDeliveryDoesNotMakePartitionClaimable(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-terminal', 'wh-1'));

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
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));

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

    public function testKeepaliveOnStolenLeaseDropsLeaseAndNextCallReclaims(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $first = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $first);

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');

        // Simulate a lease steal by clearing our lock row out-of-band.
        $this->connection->executeStatement(
            'UPDATE webhook_stream SET locked_by = NULL, lock_expires_at = NULL WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        // Keepalive sees the missing lease and drops the receiver's local lease state.
        $this->receiver->keepalive($first[0]);

        // The next poll performs a fresh claim from scratch; the already-yielded entry is
        // still QUEUED (the handler hasn't run here), so it is re-yielded.
        $second = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $second);
        $secondMsg = $second[0]->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $secondMsg);
        static::assertSame($this->ids->get('evt-1'), $secondMsg->getWebhookEventId());
    }

    public function testReleasesLeaseOnReset(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));

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

    public function testRejectLeavesRowForCrashRecovery(): void
    {
        // The webhook transport is single-handler — WebhookDeliveryService owns retry state
        // persistence. A Messenger reject() therefore signals only an exceptional bubble out
        // of the handler: the row is left in RUNNING and recovered by resetRunningForPartition
        // on the next claim.
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Webhook handler rejected unexpectedly; leaving row for crash recovery',
                static::callback(
                    fn (array $context): bool => $context['webhookEventId'] === $this->ids->get('evt-1')
                        && $context['webhookId'] === $this->ids->get('wh-1')
                        && \is_string($context['workerId'])
                        && $context['workerId'] !== ''
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
        $this->truncateHarnessTables();

        $this->createWebhook('wh-1');

        $eventLogId = Uuid::randomBytes();
        $this->connection->insert('webhook_event_log', [
            'id' => $eventLogId,
            'app_name' => null,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhook_name' => 'hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'serialized_webhook_message' => $serializedBlob,
        ]);
        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $eventLogId,
            'webhook_id' => $this->ids->getBytes('wh-1'),
            'partition_key' => $partitionKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            [
                'id' => Uuid::randomBytes(),
                'pk' => $partitionKey,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));

        static::assertSame([], $envelopes);
        static::assertSame(
            WebhookEventLogDefinition::STATUS_FAILED,
            $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => $eventLogId]
            )
        );
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function keepaliveHintProvider(): iterable
    {
        // Symfony's `--keepalive` signals with a small `$seconds` (default 5s). If we treated
        // that as an exact extension, a healthy 240s lease would collapse to 5s and leave a
        // window for another worker to steal the partition. A long hint (e.g. a long-running
        // handler signaling its own budget) must instead push the lease past current expiry.
        yield 'small hint keeps current expiry' => [5, false];
        yield 'large hint extends past current expiry' => [MySQLWebhookReceiver::LEASE_SECONDS * 3, true];
    }

    #[DataProvider('keepaliveHintProvider')]
    public function testKeepaliveHonorsMinimumHintSemantics(int $hintSeconds, bool $expectExtension): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $envelopes);

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $originalExpiry = $this->connection->fetchOne(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        $this->clock->modify('+1 seconds');
        $this->receiver->keepalive($envelopes[0], $hintSeconds);

        $expiresAfter = $this->connection->fetchOne(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        if ($expectExtension) {
            $expected = $this->clock->now()
                ->modify(\sprintf('+%d seconds', $hintSeconds))
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            static::assertSame($expected, $expiresAfter);
        } else {
            static::assertSame($originalExpiry, $expiresAfter, 'keepalive must not shrink the lease when the hint is smaller than the remaining time');
        }
    }

    public function testReusedLeaseStopsWhenOwnershipWasStolen(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));

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

        $this->outbox->ensureOutboxEntry($this->entryFor('evt-2', 'wh-1'));

        $second = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertSame([], $second);
    }

    public function testFetchStopsYieldingWhenLeaseExpiresMidBatch(): void
    {
        // A worker holding the lease must stop yielding once clock passes expiresAt —
        // another worker may already be claiming the partition via SKIP LOCKED.
        $this->truncateHarnessTables();

        $this->createWebhook('wh-1');
        for ($i = 1; $i <= 3; ++$i) {
            $this->outbox->ensureOutboxEntry($this->entryFor('evt-' . $i, 'wh-1'));
        }

        $yielded = [];
        foreach ($this->receiver->get() as $envelope) {
            $yielded[] = $envelope;
            if (\count($yielded) === 1) {
                // Jump past LEASE_SECONDS. The per-yield expiresAt guard must bail on the
                // next iteration even though currentLease is still set locally.
                $this->clock->modify(\sprintf('+%d seconds', MySQLWebhookReceiver::LEASE_SECONDS + 1));
            }
        }

        static::assertCount(1, $yielded, 'fetch() must stop once the DB lease has expired');
    }

    public function testLeaseRotatesAfterMaxMessagesPerLeaseBudget(): void
    {
        // Pins the fairness contract now that time-based rotation was removed: a worker
        // processing a hot partition must release it after MAX_MESSAGES_PER_LEASE deliveries
        // so another worker on a sibling partition gets a turn. Without this, a single-
        // partition workload could hold the lease indefinitely under our new rotation rule.
        $this->truncateHarnessTables();

        $this->createWebhook('wh-1');
        $total = MySQLWebhookReceiver::MAX_MESSAGES_PER_LEASE + 2;
        for ($i = 1; $i <= $total; ++$i) {
            $this->outbox->ensureOutboxEntry($this->entryFor('evt-' . $i, 'wh-1'));
        }

        $first = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(MySQLWebhookReceiver::MAX_MESSAGES_PER_LEASE, $first, 'one batch must cap at MAX_MESSAGES_PER_LEASE');

        foreach ($first as $envelope) {
            $message = $envelope->getMessage();
            static::assertInstanceOf(WebhookEventMessage::class, $message);
            $entry = $this->outbox->markRunning($message->getWebhookEventId());
            static::assertNotNull($entry);
            $this->outbox->markSuccess($entry, null);
        }

        // The batch budget is used up — the next fetch releases and re-claims before draining
        // the remaining entries.
        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $lockBetweenFetches = $this->connection->fetchOne(
            'SELECT locked_by FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertNotNull($lockBetweenFetches);

        $second = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(2, $second, 'the second fetch must drain the remainder after rotation');
    }

    public function testPoisonPillMarksFailedAndContinues(): void
    {
        $this->createWebhook('wh-1');

        // Insert a row with invalid serialized payload directly.
        $eventLogId = Uuid::randomBytes();
        $this->connection->insert('webhook_event_log', [
            'id' => $eventLogId,
            'app_name' => null,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhook_name' => 'hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'serialized_webhook_message' => 'not-a-valid-serialized-message',
        ]);
        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $eventLogId,
            'webhook_id' => $this->ids->getBytes('wh-1'),
            'partition_key' => $partitionKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            [
                'id' => Uuid::randomBytes(),
                'pk' => $partitionKey,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));

        // Poison pill was marked failed; partition returned empty.
        static::assertSame([], $envelopes);

        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $eventLogId]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $status);
    }

    public function testFetchEmptyAfterClaimReleasesPartition(): void
    {
        // Race: receiver acquires (or reuses) a lease successfully, but by the time fetchDue
        // runs another worker has drained the partition. The empty-fetch branch must release
        // the lease so a different partition gets a turn next tick.
        //
        // First get() claims the partition and yields the entry. Between calls we mutate the
        // delivery row so its predicate no longer satisfies fetchDue's claimable filter, while
        // leaving the webhook_stream lock row intact. The second get() reuses the live lease
        // (ensureLeaseOwnership succeeds), runs fetchDue, gets [], and falls through to
        // releaseLease — observable by locked_by/lock_expires_at clearing on the stream row.
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));

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

        // Move the entry's next_retry_at far into the future so fetchDue's
        // `next_retry_at <= :now` predicate excludes it. Status remains QUEUED, so this
        // simulates a sibling worker pushing the row out of the due-set without changing
        // the webhook_stream lock.
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
        static::assertNull($lockAfter['locked_by'], 'empty fetch must release the partition lease');
        static::assertNull($lockAfter['lock_expires_at']);
    }

    public function testEventIdMismatchInBlobIsDropped(): void
    {
        // DB row's webhook_event_log_id and the serialized blob's webhookEventId disagree —
        // a botched migration or manual SQL splice. toEnvelope must refuse to trust the blob
        // and fail the entry rather than emitting a message whose identity doesn't match the
        // tracked row.
        $this->truncateHarnessTables();
        $this->createWebhook('wh-1');

        $rowEventId = $this->ids->get('evt-row');
        $blobEventId = $this->ids->get('evt-blob');
        static::assertNotSame($rowEventId, $blobEventId);

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

        $this->connection->insert('webhook_event_log', [
            'id' => Uuid::fromHexToBytes($rowEventId),
            'app_name' => null,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhook_name' => 'hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'serialized_webhook_message' => serialize($blobMessage),
        ]);
        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => Uuid::fromHexToBytes($rowEventId),
            'webhook_id' => $this->ids->getBytes('wh-1'),
            'partition_key' => $partitionKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            [
                'id' => Uuid::randomBytes(),
                'pk' => $partitionKey,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Discarded unreadable webhook delivery',
                static::callback(
                    static fn (array $context): bool => $context['webhookEventId'] === $rowEventId
                        && \is_string($context['workerId'])
                        && $context['workerId'] !== ''
                )
            );
        $receiver = new MySQLWebhookReceiver($this->lockService, $this->outbox, $this->clock, $logger);

        $envelopes = iterator_to_array($this->asGenerator($receiver->get()));
        static::assertSame([], $envelopes);

        // Entry was failed against the row's id — markUndeliverableFetchedEntryFailed updates
        // the event_log row and deletes the delivery row.
        static::assertSame(
            WebhookEventLogDefinition::STATUS_FAILED,
            $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($rowEventId)]
            )
        );
        $deliveryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($rowEventId)]
        );
        static::assertSame(0, $deliveryCount);
    }

    public function testTypedPropertyMismatchBlobIsDropped(): void
    {
        // PHP 8+ throws \TypeError when unserialize assigns a wrong-typed value to a typed
        // readonly property. Without the `catch (\Error)` in toEnvelope(), a single
        // migration-corrupted blob would kill the worker process (Symfony Messenger treats
        // the receiver's generator as the worker boundary) and stall the partition.
        $this->truncateHarnessTables();
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

        // `url` is a non-nullable `string` on WebhookEventMessage. Swapping its serialized
        // value for `N;` (null) forces unserialize's typed-property check to throw.
        $urlValue = 'https://example.com/webhook';
        $needle = \sprintf('s:%d:"%s";', \strlen($urlValue), $urlValue);
        static::assertStringContainsString($needle, $blob, 'serialize output shape changed; test needs rework');
        $tamperedBlob = str_replace($needle, 'N;', $blob);
        static::assertNotSame($blob, $tamperedBlob);

        $this->connection->insert('webhook_event_log', [
            'id' => Uuid::fromHexToBytes($eventId),
            'app_name' => null,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhook_name' => 'hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'serialized_webhook_message' => $tamperedBlob,
        ]);
        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => Uuid::fromHexToBytes($eventId),
            'webhook_id' => $this->ids->getBytes('wh-1'),
            'partition_key' => $partitionKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            [
                'id' => Uuid::randomBytes(),
                'pk' => $partitionKey,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        // Must not throw — the contract is "drop the row, keep the worker alive".
        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertSame([], $envelopes);

        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($eventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $status);

        $deliveryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($eventId)]
        );
        static::assertSame(0, $deliveryCount);
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
     * setUp uses container-wired services that bypass IntegrationTestBehaviour's transaction,
     * so per-test row pollution has to be scrubbed explicitly.
     */
    private function truncateHarnessTables(): void
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
