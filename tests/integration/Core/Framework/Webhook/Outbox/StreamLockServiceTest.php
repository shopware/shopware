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
use Shopware\Core\Framework\Webhook\Outbox\StreamLease;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
class StreamLockServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private StreamLockService $service;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $this->service = new StreamLockService($this->connection, $this->clock);
        $this->clearWebhookState();
    }

    public function testClaimNextClaimsPartitionWithDueDelivery(): void
    {
        $partitionKey = $this->makePartitionKey('app-c');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_QUEUED);

        $lease = $this->service->claimNext('worker-1', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);

        static::assertNotNull($lease);
        static::assertSame($partitionKey, $lease->partitionKey);
        static::assertSame('worker-1', $lease->workerId);

        $row = $this->connection->fetchAssociative(
            'SELECT locked_by, lock_expires_at, last_claimed_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertNotFalse($row);
        static::assertSame('worker-1', $row['locked_by']);
        static::assertNotNull($row['lock_expires_at']);
        static::assertNotNull($row['last_claimed_at']);
    }

    public function testClaimNextSkipsAlreadyLockedPartitions(): void
    {
        $pkA = $this->makePartitionKey('app-d');
        $pkB = $this->makePartitionKey('app-e');

        $this->insertStream($pkA);
        $this->insertStream($pkB);
        $this->insertDeliveryRow($pkA, WebhookEventLogDefinition::STATUS_QUEUED);
        $this->insertDeliveryRow($pkB, WebhookEventLogDefinition::STATUS_QUEUED);

        $freshExpiry = $this->clock->now()->modify('+120 seconds')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->executeStatement(
            'UPDATE webhook_stream SET locked_by = :worker, lock_expires_at = :exp WHERE partition_key = :pk',
            ['worker' => 'other-worker', 'exp' => $freshExpiry, 'pk' => $pkA]
        );

        $lease = $this->service->claimNext('worker-2', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);

        static::assertNotNull($lease);
        static::assertSame($pkB, $lease->partitionKey);
    }

    public function testClaimNextRescuesPartitionWithOnlyStaleRunningRow(): void
    {
        // Worker died on the last row in this partition; nothing QUEUED/PENDING_RETRY
        // remains. Without the stale-RUNNING branch in claimNext, the row would be
        // stranded until an unrelated event dispatched to the same partition.
        $partitionKey = $this->makePartitionKey('app-stranded');
        $this->insertStream($partitionKey);
        $eventLogId = $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_RUNNING);

        $staleLastAttempt = $this->clock->now()->modify('-190 seconds')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET last_attempt_at = :at WHERE webhook_event_log_id = :id',
            ['at' => $staleLastAttempt, 'id' => $eventLogId]
        );

        $lease = $this->service->claimNext(
            'rescue-worker',
            180,
            [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]
        );

        static::assertNotNull($lease);
        static::assertSame($partitionKey, $lease->partitionKey);
        static::assertSame('rescue-worker', $lease->workerId);
    }

    public function testClaimNextSkipsPartitionWithOnlyFreshRunningRow(): void
    {
        // The active holder's lease hasn't expired yet — last_attempt_at is recent.
        // claimNext must not steal the partition from a worker that may still be alive.
        $partitionKey = $this->makePartitionKey('app-inflight');
        $this->insertStream($partitionKey);
        $eventLogId = $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_RUNNING);

        $freshLastAttempt = $this->clock->now()->modify('-5 seconds')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET last_attempt_at = :at WHERE webhook_event_log_id = :id',
            ['at' => $freshLastAttempt, 'id' => $eventLogId]
        );

        $lease = $this->service->claimNext(
            'poacher-worker',
            180,
            [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]
        );

        static::assertNull($lease);
    }

    public function testHeartbeatExtendsLease(): void
    {
        $partitionKey = $this->makePartitionKey('app-hb');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_QUEUED);

        $lease = $this->service->claimNext('worker-hb', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);
        static::assertNotNull($lease);

        $expected = $this->clock->now()->modify('+120 seconds');
        $renewed = $this->service->heartbeat($lease, 120);

        static::assertInstanceOf(StreamLease::class, $renewed);
        static::assertEqualsWithDelta($expected->getTimestamp(), $renewed->expiresAt->getTimestamp(), 2);

        $row = $this->connection->fetchAssociative(
            'SELECT lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertNotFalse($row);

        $dbExpiresAt = new \DateTimeImmutable($row['lock_expires_at']);
        static::assertEqualsWithDelta($expected->getTimestamp(), $dbExpiresAt->getTimestamp(), 2);
    }

    public function testHeartbeatReturnsNullWhenLeaseStolen(): void
    {
        $partitionKey = $this->makePartitionKey('app-stolen');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_QUEUED);

        $lease = $this->service->claimNext('worker-a', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);
        static::assertNotNull($lease);

        $this->connection->executeStatement(
            'UPDATE webhook_stream SET locked_by = :thief WHERE partition_key = :pk',
            ['thief' => 'thief-worker', 'pk' => $partitionKey]
        );

        $renewed = $this->service->heartbeat($lease, 60);

        static::assertNull($renewed);
    }

    /**
     * @return iterable<string, array{locked: bool, ageSeconds: int, hasDeliveries: bool, expectedDeleted: int}>
     */
    public static function deleteOrphanedStreamsProvider(): iterable
    {
        yield 'old orphan with no deliveries is deleted' => [
            'locked' => false,
            'ageSeconds' => StreamLockService::ORPHAN_GRACE_SECONDS * 2,
            'hasDeliveries' => false,
            'expectedDeleted' => 1,
        ];

        yield 'orphan with deliveries is kept' => [
            'locked' => false,
            'ageSeconds' => StreamLockService::ORPHAN_GRACE_SECONDS * 2,
            'hasDeliveries' => true,
            'expectedDeleted' => 0,
        ];

        yield 'actively locked stream is kept' => [
            'locked' => true,
            'ageSeconds' => StreamLockService::ORPHAN_GRACE_SECONDS * 2,
            'hasDeliveries' => false,
            'expectedDeleted' => 0,
        ];

        yield 'stream younger than grace period is kept' => [
            'locked' => false,
            'ageSeconds' => \intdiv(StreamLockService::ORPHAN_GRACE_SECONDS, 2),
            'hasDeliveries' => false,
            'expectedDeleted' => 0,
        ];
    }

    #[DataProvider('deleteOrphanedStreamsProvider')]
    public function testDeleteOrphanedStreams(bool $locked, int $ageSeconds, bool $hasDeliveries, int $expectedDeleted): void
    {
        $partitionKey = $this->makePartitionKey('orphan-' . Uuid::randomHex());
        $createdAt = $this->clock->now()->modify(\sprintf('-%d seconds', $ageSeconds))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        if ($locked) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO webhook_stream (id, partition_key, locked_by, lock_expires_at, created_at) VALUES (:id, :pk, :worker, :exp, :now)',
                [
                    'id' => Uuid::randomBytes(),
                    'pk' => $partitionKey,
                    'worker' => 'worker-lock',
                    'exp' => $this->clock->now()->modify('+120 seconds')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'now' => $createdAt,
                ]
            );
        } else {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
                ['id' => Uuid::randomBytes(), 'pk' => $partitionKey, 'now' => $createdAt]
            );
        }

        if ($hasDeliveries) {
            $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_QUEUED);
        }

        $deleted = $this->service->deleteOrphanedStreams(100);

        static::assertSame($expectedDeleted, $deleted);
        $stillExists = (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_stream WHERE partition_key = :pk', ['pk' => $partitionKey]);
        static::assertSame($expectedDeleted === 0, $stillExists);
    }

    public function testClaimNextReturnsNullWhenPartitionHasOnlyRunningDeliveries(): void
    {
        $partitionKey = $this->makePartitionKey('running-only-partition');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_RUNNING);

        $lease = $this->service->claimNext('worker-1', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);

        static::assertNull($lease, 'claimNext must return null when partition only has RUNNING deliveries');
    }

    private function makePartitionKey(string $seed): string
    {
        return Hasher::hashBinary($seed, 'xxh128');
    }

    private function clearWebhookState(): void
    {
        $this->connection->executeStatement('DELETE FROM webhook_stream');
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook');
    }

    private function insertStream(string $partitionKey): void
    {
        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            [
                'id' => Uuid::randomBytes(),
                'pk' => $partitionKey,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function insertDeliveryRow(string $partitionKey, string $status, string $nextRetryAtModifier = ''): string
    {
        $webhookId = $this->createWebhook();
        $eventLogId = Uuid::randomBytes();
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $eventLogId,
            'delivery_status' => $status,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'app_name' => null,
            'url' => 'https://example.com/webhook',
            'serialized_webhook_message' => serialize('test'),
            'created_at' => $now,
        ]);

        $nextRetryAt = $nextRetryAtModifier !== ''
            ? $this->clock->now()->modify($nextRetryAtModifier)->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            : null;

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $eventLogId,
            'webhook_id' => $webhookId,
            'partition_key' => $partitionKey,
            'delivery_status' => $status,
            'execution_count' => 0,
            'next_retry_at' => $nextRetryAt,
            'created_at' => $now,
        ]);

        return $eventLogId;
    }

    private function createWebhook(): string
    {
        $id = Uuid::randomBytes();
        $this->connection->insert('webhook', [
            'id' => $id,
            'name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'app_id' => null,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }
}
