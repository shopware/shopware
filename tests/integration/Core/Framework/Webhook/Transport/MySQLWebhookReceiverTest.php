<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Transport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
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

    private OutboxEventRepository $outbox;

    private StreamLockService $lockService;

    private MySQLWebhookReceiver $receiver;

    private MockClock $clock;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-04-20 10:00:00'));
        $this->outbox = new OutboxEventRepository($this->connection, $this->clock);
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
        $this->outbox->markSuccess($this->ids->get('evt-1'));

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
        static::assertTrue($this->outbox->markSuccess($this->ids->get('evt-terminal'), null, $entry->executionCount, $entry->sequence));

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
        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $envelopes);

        $this->outbox->markRunning($this->ids->get('evt-1'));
        $this->receiver->reject($envelopes[0]);

        $row = $this->connection->fetchAssociative(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($row);
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $row['delivery_status']);
    }

    public function testRejectSkipsUnexpectedEnvelope(): void
    {
        // Defensive: if Symfony ever routes a non-WebhookEventMessage envelope through this
        // transport, the receiver must not fatal on a missing method call.
        $this->expectNotToPerformAssertions();
        $receiver = new MySQLWebhookReceiver($this->lockService, $this->outbox, $this->clock, new NullLogger());

        $receiver->reject(new Envelope(new \stdClass()));
    }

    public function testFetchStopsYieldingWhenLeaseAbandonedMidBatch(): void
    {
        $this->truncateHarnessTables();

        // Insert several messages so the generator yields more than one envelope.
        $this->createWebhook('wh-1');
        for ($i = 1; $i <= 3; ++$i) {
            $this->outbox->ensureOutboxEntry($this->entryFor('evt-' . $i, 'wh-1'));
        }

        $yielded = [];
        foreach ($this->receiver->get() as $envelope) {
            $yielded[] = $envelope;
            if (\count($yielded) === 1) {
                // Simulate keepalive detecting a stolen lease — abandonLease clears currentLease.
                $ref = new \ReflectionProperty(MySQLWebhookReceiver::class, 'currentLease');
                $ref->setValue($this->receiver, null);
            }
        }

        static::assertCount(1, $yielded, 'fetch() must stop yielding once the lease is abandoned');
    }

    public function testBrokenMessageIsMarkedAsFailed(): void
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
            'serialized_webhook_message' => serialize(new \stdClass()),
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

    public function testKeepaliveDoesNotShrinkLeaseWhenHintIsSmallerThanRemaining(): void
    {
        // Symfony's `--keepalive` signals with a small `$seconds` (default 5s). If we treated
        // that as an exact extension, a healthy 240s lease would collapse to 5s and leave a
        // window for another worker to steal the partition. Keep the lease at its current
        // expiry when the hint is smaller than what we already have.
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
        $this->receiver->keepalive($envelopes[0], 5);

        $expiresAfter = $this->connection->fetchOne(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        static::assertSame($originalExpiry, $expiresAfter, 'keepalive must not shrink the lease when the hint is smaller than the remaining time');
    }

    public function testKeepaliveExtendsLeaseWhenHintExceedsRemaining(): void
    {
        // Symfony's contract says `$seconds` is the minimum duration the message must stay
        // alive. A long-running handler that signals its own budget (e.g. 720s) must push
        // the lease out past the current expiry, not get clamped to it.
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $envelopes);

        $longHint = MySQLWebhookReceiver::LEASE_SECONDS * 3;
        $this->clock->modify('+1 seconds');
        $this->receiver->keepalive($envelopes[0], $longHint);

        $partitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        $expiresAt = $this->connection->fetchOne(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );

        $expected = $this->clock->now()
            ->modify(\sprintf('+%d seconds', $longHint))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        static::assertSame($expected, $expiresAt);
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
            $this->outbox->markSuccess($message->getWebhookEventId());
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

    public function testBlobEventIdMismatchIsDropped(): void
    {
        // A well-formed serialized WebhookEventMessage whose internal eventId does not match
        // the row we leased it from must be dropped — otherwise markSuccess/markFailed on the
        // blob's id could mutate a completely different event's state (migration-corrupted
        // blob, SQL-write tampering).
        $this->truncateHarnessTables();
        $this->createWebhook('wh-1');

        $rowEventId = $this->ids->get('row-evt');
        $blobEventId = $this->ids->get('blob-evt');
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

        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertSame([], $envelopes);

        $rowStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($rowEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $rowStatus);

        $blobRowExists = $this->connection->fetchOne(
            'SELECT id FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($blobEventId)]
        );
        static::assertFalse($blobRowExists, 'the unrelated blob event must not have been created by the mismatch path');

        $deliveryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($rowEventId)]
        );
        static::assertSame(0, $deliveryCount, 'delivery row of the leased event must be deleted');
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
