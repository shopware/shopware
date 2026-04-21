<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Outbox;

use Doctrine\DBAL\Connection;
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
    }

    public function testClaimNextReturnsNullWhenNoStreamsExist(): void
    {
        $lease = $this->service->claimNext('worker-1', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);

        static::assertNull($lease);
    }

    public function testClaimNextReturnsNullWhenNoDueDeliveries(): void
    {
        $partitionKey = $this->makePartitionKey('app-a');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_SUCCESS);

        $lease = $this->service->claimNext('worker-1', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);

        static::assertNull($lease);
    }

    public function testClaimNextReturnsNullWhenPendingRetryInFuture(): void
    {
        $partitionKey = $this->makePartitionKey('app-b');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_PENDING_RETRY, '+5 minutes');

        $lease = $this->service->claimNext('worker-1', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);

        static::assertNull($lease);
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

    public function testClaimNextReclaimsExpiredLocks(): void
    {
        $partitionKey = $this->makePartitionKey('app-f');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_QUEUED);

        $expiredExpiry = $this->clock->now()->modify('-5 seconds')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->executeStatement(
            'UPDATE webhook_stream SET locked_by = :worker, lock_expires_at = :exp WHERE partition_key = :pk',
            ['worker' => 'old-worker', 'exp' => $expiredExpiry, 'pk' => $partitionKey]
        );

        $lease = $this->service->claimNext('new-worker', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);

        static::assertNotNull($lease);
        static::assertSame('new-worker', $lease->workerId);
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

    public function testReleaseClearsLock(): void
    {
        $partitionKey = $this->makePartitionKey('app-rel');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_QUEUED);

        $lease = $this->service->claimNext('worker-rel', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);
        static::assertNotNull($lease);

        $this->service->release($lease);

        $row = $this->connection->fetchAssociative(
            'SELECT locked_by, lock_expires_at FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertNotFalse($row);
        static::assertNull($row['locked_by']);
        static::assertNull($row['lock_expires_at']);
    }

    public function testReleaseNoOpWhenLeaseStolen(): void
    {
        $partitionKey = $this->makePartitionKey('app-noop');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_QUEUED);

        $lease = $this->service->claimNext('worker-x', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);
        static::assertNotNull($lease);

        $this->connection->executeStatement(
            'UPDATE webhook_stream SET locked_by = :thief WHERE partition_key = :pk',
            ['thief' => 'thief-worker', 'pk' => $partitionKey]
        );

        $this->service->release($lease);

        $lockedBy = $this->connection->fetchOne(
            'SELECT locked_by FROM webhook_stream WHERE partition_key = :pk',
            ['pk' => $partitionKey]
        );
        static::assertSame('thief-worker', $lockedBy, 'Release must not clear a stolen lease');
    }

    public function testHeartbeatSucceedsAfterExpiryIfLeaseNotStolen(): void
    {
        $partitionKey = $this->makePartitionKey('app-hb-expire');
        $this->insertStream($partitionKey);
        $this->insertDeliveryRow($partitionKey, WebhookEventLogDefinition::STATUS_QUEUED);

        $lease = $this->service->claimNext('worker-1', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);
        static::assertNotNull($lease);

        $this->clock->modify('+120 seconds');

        $renewed = $this->service->heartbeat($lease, 60);
        static::assertInstanceOf(StreamLease::class, $renewed, 'Heartbeat must succeed if our worker_id is still the lock holder, even if lock_expires_at passed');

        static::assertGreaterThan($this->clock->now()->getTimestamp(), $renewed->expiresAt->getTimestamp());
    }

    public function testDeleteOrphanedStreamsRemovesStreamsWithoutDeliveries(): void
    {
        $orphanKey = $this->makePartitionKey('orphan-partition');
        $activeKey = $this->makePartitionKey('active-partition');

        // Use a timestamp older than the grace period so it is eligible for deletion
        $oldEnough = $this->clock->now()->modify(\sprintf('-%d seconds', StreamLockService::ORPHAN_GRACE_SECONDS * 2))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            ['id' => Uuid::randomBytes(), 'pk' => $orphanKey, 'now' => $oldEnough]
        );

        $webhookId = Uuid::randomBytes();
        $this->connection->insert('webhook', [
            'id' => $webhookId,
            'name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'app_id' => null,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $eventLogId = Uuid::randomBytes();
        $this->connection->insert('webhook_event_log', [
            'id' => $eventLogId,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'serialized_webhook_message' => serialize('test'),
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $eventLogId,
            'webhook_id' => $webhookId,
            'partition_key' => $activeKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'execution_count' => 0,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            ['id' => Uuid::randomBytes(), 'pk' => $activeKey, 'now' => $oldEnough]
        );

        $deleted = $this->service->deleteOrphanedStreams(100);

        static::assertSame(1, $deleted);
        static::assertFalse((bool) $this->connection->fetchOne('SELECT 1 FROM webhook_stream WHERE partition_key = :pk', ['pk' => $orphanKey]));
        static::assertTrue((bool) $this->connection->fetchOne('SELECT 1 FROM webhook_stream WHERE partition_key = :pk', ['pk' => $activeKey]));
    }

    public function testDeleteOrphanedStreamsKeepsActivelyLockedStreams(): void
    {
        $lockedKey = $this->makePartitionKey('locked-orphan');
        $now = $this->clock->now();
        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (partition_key, locked_by, lock_expires_at, created_at) VALUES (:pk, :worker, :exp, :now)',
            [
                'pk' => $lockedKey,
                'worker' => 'worker-lock',
                'exp' => $now->modify('+120 seconds')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $deleted = $this->service->deleteOrphanedStreams(100);

        static::assertSame(0, $deleted);
        static::assertTrue((bool) $this->connection->fetchOne('SELECT 1 FROM webhook_stream WHERE partition_key = :pk', ['pk' => $lockedKey]));
    }

    public function testDeleteOrphanedStreamsPreservesStreamsYoungerThanGracePeriod(): void
    {
        $youngKey = $this->makePartitionKey('young-orphan-grace');
        $recentCreatedAt = $this->clock->now()->modify(\sprintf('-%d seconds', \intdiv(StreamLockService::ORPHAN_GRACE_SECONDS, 2)))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            ['id' => Uuid::randomBytes(), 'pk' => $youngKey, 'now' => $recentCreatedAt]
        );

        $deleted = $this->service->deleteOrphanedStreams(100);

        static::assertSame(0, $deleted, \sprintf('Stream younger than %d-second grace period must not be deleted', StreamLockService::ORPHAN_GRACE_SECONDS));
        static::assertTrue((bool) $this->connection->fetchOne('SELECT 1 FROM webhook_stream WHERE partition_key = :pk', ['pk' => $youngKey]));
    }

    public function testDeleteOrphanedStreamsDeletesStreamsOlderThanGracePeriod(): void
    {
        $oldKey = $this->makePartitionKey('old-orphan-grace');
        $oldCreatedAt = $this->clock->now()->modify(\sprintf('-%d seconds', StreamLockService::ORPHAN_GRACE_SECONDS * 2))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->executeStatement(
            'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
            ['id' => Uuid::randomBytes(), 'pk' => $oldKey, 'now' => $oldCreatedAt]
        );

        $deleted = $this->service->deleteOrphanedStreams(100);

        static::assertSame(1, $deleted, \sprintf('Stream older than %d-second grace period with no deliveries must be deleted', StreamLockService::ORPHAN_GRACE_SECONDS));
        static::assertFalse((bool) $this->connection->fetchOne('SELECT 1 FROM webhook_stream WHERE partition_key = :pk', ['pk' => $oldKey]));
    }

    public function testTwoWorkersClaimDifferentPartitionsUnderContention(): void
    {
        $pkA = $this->makePartitionKey('contention-app-a');
        $pkB = $this->makePartitionKey('contention-app-b');

        $this->insertStream($pkA);
        $this->insertStream($pkB);
        $this->insertDeliveryRow($pkA, WebhookEventLogDefinition::STATUS_QUEUED);
        $this->insertDeliveryRow($pkB, WebhookEventLogDefinition::STATUS_QUEUED);

        // Two separate service instances (same connection is fine for non-blocking contention)
        $serviceA = new StreamLockService($this->connection, $this->clock);
        $serviceB = new StreamLockService($this->connection, $this->clock);

        $leaseA = $serviceA->claimNext('worker-a', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);
        $leaseB = $serviceB->claimNext('worker-b', 60, [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]);

        static::assertNotNull($leaseA, 'Worker A must claim a partition');
        static::assertNotNull($leaseB, 'Worker B must claim a partition');
        static::assertNotSame($leaseA->partitionKey, $leaseB->partitionKey, 'Workers must claim different partitions');
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
