<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Transport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookStateRepository;
use Shopware\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

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
        // Wire a delivery service with the mock clock so reject()'s retry-at computation
        // stays predictable; the other deps we don't exercise in this test class.
        $container = static::getContainer();
        $deliveryService = new WebhookDeliveryService(
            $container->get(WebhookClient::class),
            $container->get(AppPayloadServiceHelper::class),
            $this->outbox,
            new RetryDelayCalculator($this->clock),
            $container->get(MessageBusInterface::class),
            $container->get(WebhookStateRepository::class),
            new NullLogger(),
            false,
        );
        $this->receiver = new MySQLWebhookReceiver($this->lockService, $this->outbox, $deliveryService, $this->clock, new NullLogger());
        $this->ids = new IdsCollection();
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

    public function testRejectResetsRowWithBackoff(): void
    {
        $this->createWebhook('wh-1');
        $this->outbox->ensureOutboxEntry($this->entryFor('evt-1', 'wh-1'));
        $envelopes = iterator_to_array($this->asGenerator($this->receiver->get()));
        static::assertCount(1, $envelopes);

        $this->outbox->markRunning($this->ids->get('evt-1'));

        $this->receiver->reject($envelopes[0]);

        $row = $this->connection->fetchAssociative(
            'SELECT delivery_status, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertNotFalse($row);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $row['delivery_status']);
        static::assertNotNull($row['next_retry_at']);

        $expected = $this->clock->now()->modify('+5 seconds')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        static::assertSame($expected, $row['next_retry_at']);
    }

    public function testRejectMarksFailedOnceExecutionCountPastMaxRetries(): void
    {
        $this->createWebhook('wh-1');
        $message = new WebhookEventMessage(
            $this->ids->get('evt-1'),
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
        $this->outbox->ensureOutboxEntry(new OutboxInsert(
            $message->getWebhookEventId(),
            $message->getWebhookId(),
            Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            serialize($message),
        ));

        // Burn through MAX_RETRIES attempts so the next reject tips over the threshold.
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET execution_count = :count, delivery_status = :status WHERE webhook_event_log_id = :id',
            [
                'count' => WebhookDeliveryService::MAX_RETRIES + 1,
                'status' => WebhookEventLogDefinition::STATUS_RUNNING,
                'id' => $this->ids->getBytes('evt-1'),
            ]
        );

        $this->receiver->reject(new Envelope($message));

        $eventLogStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $eventLogStatus);

        $deliveryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );
        static::assertSame(0, $deliveryCount, 'delivery row must be deleted after terminal markFailed');
    }

    public function testFetchStopsYieldingWhenLeaseAbandonedMidBatch(): void
    {
        // Isolate from leftover stream/delivery rows left by earlier test files: the
        // container-wired services in setUp bypass IntegrationTestBehaviour's transaction.
        $this->connection->executeStatement('DELETE FROM webhook_stream');
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook');

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
