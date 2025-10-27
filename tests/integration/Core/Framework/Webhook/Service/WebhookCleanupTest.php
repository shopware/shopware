<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Service\WebhookCleanup;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(WebhookCleanup::class)]
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
        $cleanup = new WebhookCleanup($systemConfigService, $this->connection, new MockClock($mockedDate));

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
