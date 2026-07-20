<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopware\Core\Framework\Webhook\Service\WebhookCleanup;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
class WebhookCleanupTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    public function testRemoveOldLogs(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $systemConfigService = new StaticSystemConfigService([
            'core.webhook.entryLifetimeSeconds' => 3600, // 1 hour
        ]);
        $mockedDate = new \DateTimeImmutable('2 January 2023 13:00');
        $streamLockService = static::getContainer()->get(StreamLockService::class);
        $cleanup = new WebhookCleanup($systemConfigService, $this->connection, $streamLockService, new MockClock($mockedDate));

        $this->connection->executeStatement('DELETE FROM webhook_event_log');

        $beforeLifetime = $mockedDate->modify('- 59 min')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $afterLifetime = $mockedDate->modify('- 61 min')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $afterDoubleLifetime = $mockedDate->modify('- 121 min')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        // Insert entries
        $this->insertLog('success_recent', $beforeLifetime, WebhookEventLogDefinition::STATUS_SUCCESS);
        $this->insertLog('running_old', $afterLifetime, WebhookEventLogDefinition::STATUS_RUNNING);
        $this->insertLog('success_old', $afterLifetime, WebhookEventLogDefinition::STATUS_SUCCESS);
        $this->insertLog('failed_very_old', $afterDoubleLifetime, WebhookEventLogDefinition::STATUS_FAILED);
        $this->insertLog('queued_old', $afterLifetime, WebhookEventLogDefinition::STATUS_QUEUED);
        $this->insertLog('queued_very_old', $afterDoubleLifetime, WebhookEventLogDefinition::STATUS_QUEUED);

        $cleanup->removeOldLogs();

        $remaining = $this->connection->fetchAllKeyValue('SELECT event_name, delivery_status FROM webhook_event_log');

        static::assertCount(3, $remaining);
        // To new to be cleaned up
        static::assertArrayHasKey('success_recent', $remaining);
        // To new to be cleaned up, queued entries are only cleaned up after double lifetime
        static::assertArrayHasKey('queued_old', $remaining);
        // Running is never cleaned up
        static::assertArrayHasKey('running_old', $remaining);
    }

    public function testRemoveOldLogsAlsoDeletesOrphanStreams(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        $mockedDate = new \DateTimeImmutable('2026-04-15 12:00:00');
        $clock = new MockClock($mockedDate);
        $streamLockService = static::getContainer()->get(StreamLockService::class);

        $systemConfigService = new StaticSystemConfigService([
            'core.webhook.entryLifetimeSeconds' => 3600,
        ]);

        // Orphan stream (older than grace period + no deliveries)
        $orphanKey = Hasher::hashBinary('orphan-app', 'xxh128');
        $this->connection->insert('webhook_stream', [
            'id' => Uuid::randomBytes(),
            'partition_key' => $orphanKey,
            'created_at' => $mockedDate->modify('-2 hours')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Active stream via raw inserts
        $webhookId = Uuid::randomBytes();
        $this->connection->insert('webhook', [
            'id' => $webhookId,
            'name' => 'active-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'app_id' => null,
            'created_at' => $mockedDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $eventLogId = Uuid::randomBytes();
        $this->connection->insert('webhook_event_log', [
            'id' => $eventLogId,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'event_name' => 'product.written',
            'webhook_name' => 'active-hook',
            'url' => 'https://example.com/webhook',
            'created_at' => $mockedDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $activeKey = Hasher::hashBinary('active-app', 'xxh128');
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $eventLogId,
            'webhook_id' => $webhookId,
            'partition_key' => $activeKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'execution_count' => 0,
            'created_at' => $mockedDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->insert('webhook_stream', [
            'id' => Uuid::randomBytes(),
            'partition_key' => $activeKey,
            'created_at' => $mockedDate->modify('-2 hours')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $cleanup = new WebhookCleanup($systemConfigService, $this->connection, $streamLockService, $clock);
        $cleanup->removeOldLogs();

        static::assertFalse(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_stream WHERE partition_key = :pk', ['pk' => $orphanKey]),
            'Orphan stream must be deleted'
        );
        static::assertTrue(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_stream WHERE partition_key = :pk', ['pk' => $activeKey]),
            'Active stream must remain'
        );
    }

    public function testRemoveOldLogsPreservesQueuedLogsWithActiveDelivery(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        $mockedDate = new \DateTimeImmutable('2026-04-15 12:00:00');
        $streamLockService = static::getContainer()->get(StreamLockService::class);

        $systemConfigService = new StaticSystemConfigService([
            'core.webhook.entryLifetimeSeconds' => 3600,
        ]);

        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook');

        $webhookId = Uuid::randomBytes();
        $this->connection->insert('webhook', [
            'id' => $webhookId,
            'name' => 'queued-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'app_id' => null,
            'created_at' => $mockedDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $eventLogId = Uuid::randomBytes();
        $oldQueuedAt = $mockedDate->modify('-3 hours')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->insert('webhook_event_log', [
            'id' => $eventLogId,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'event_name' => 'product.written',
            'webhook_name' => 'queued-hook',
            'url' => 'https://example.com/webhook',
            'created_at' => $oldQueuedAt,
        ]);

        $partitionKey = Hasher::hashBinary('active-queued', 'xxh128');
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $eventLogId,
            'webhook_id' => $webhookId,
            'partition_key' => $partitionKey,
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'execution_count' => 0,
            'created_at' => $oldQueuedAt,
        ]);

        $cleanup = new WebhookCleanup($systemConfigService, $this->connection, $streamLockService, new MockClock($mockedDate));
        $cleanup->removeOldLogs();

        static::assertTrue(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_event_log WHERE id = :id', ['id' => $eventLogId]),
            'Queued event logs with an active webhook_delivery row are still the hot queue and must not be cleaned'
        );
        static::assertTrue(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id', ['id' => $eventLogId]),
            'Active webhook_delivery row must remain with its event log'
        );
    }

    private function insertLog(string $name, string $createdAt, string $status): void
    {
        $this->connection->insert('webhook_event_log', [
            'id' => Uuid::randomBytes(),
            'created_at' => $createdAt,
            'delivery_status' => $status,
            'event_name' => $name,
            'webhook_name' => $name,
            'url' => 'http://localhost',
            'request_content' => '{}',
            'response_content' => '{}',
            'response_status_code' => 200,
        ]);
    }
}
