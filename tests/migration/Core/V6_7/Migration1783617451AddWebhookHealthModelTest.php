<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1783617451AddWebhookHealthModel;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1783617451AddWebhookHealthModel::class)]
class Migration1783617451AddWebhookHealthModelTest extends TestCase
{
    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $createdWebhookIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdWebhookIds as $id) {
            $this->connection->delete('webhook', ['id' => $id]);
        }

        parent::tearDown();
    }

    public function testBackfillsBoundaryStates(): void
    {
        $threshold = Migration1783617451AddWebhookHealthModel::DEFAULT_DEGRADED_THRESHOLD;
        $inactive = $this->insertWebhook(active: 0, errorCount: $threshold);
        $degraded = $this->insertWebhook(active: 1, errorCount: $threshold);
        $healthy = $this->insertWebhook(active: 1, errorCount: $threshold - 1);

        $this->migrate();

        $inactiveHealth = $this->fetchHealth($inactive);
        static::assertSame('disabled', $inactiveHealth['endpoint_state']);
        static::assertSame('escalation', $inactiveHealth['disabled_origin']);
        static::assertNotNull($inactiveHealth['disabled_since']);
        static::assertNull($inactiveHealth['cooldown_until']);

        $degradedHealth = $this->fetchHealth($degraded);
        static::assertSame('degraded', $degradedHealth['endpoint_state']);
        static::assertSame($threshold, (int) $degradedHealth['consecutive_transient_failures']);
        static::assertNotNull($degradedHealth['cooldown_until']);

        $healthyHealth = $this->fetchHealth($healthy);
        static::assertSame('healthy', $healthyHealth['endpoint_state']);
        static::assertSame($threshold - 1, (int) $healthyHealth['consecutive_transient_failures']);
        static::assertNull($healthyHealth['cooldown_until']);
    }

    public function testRerunPreservesExistingRowsAndBackfillsMissingRows(): void
    {
        $existing = $this->insertWebhook(active: 0, errorCount: 0);

        $this->migrate();

        $this->connection->executeStatement(
            'UPDATE webhook_health
             SET endpoint_state = :degraded, consecutive_transient_failures = 3,
                 disabled_since = NULL, disabled_origin = NULL, updated_at = :now
             WHERE webhook_id = :id',
            ['degraded' => 'degraded', 'now' => '2026-01-02 00:00:00.000', 'id' => $existing]
        );

        $missing = $this->insertWebhook(active: 1, errorCount: 0);
        $this->migrate();

        $existingHealth = $this->fetchHealth($existing);
        static::assertSame('degraded', $existingHealth['endpoint_state']);
        static::assertSame(3, (int) $existingHealth['consecutive_transient_failures']);
        static::assertSame('healthy', $this->fetchHealth($missing)['endpoint_state']);
    }

    public function testCreatesRequiredSchemaAndCascade(): void
    {
        $webhookId = $this->insertWebhook(active: 1, errorCount: 0);
        $this->migrate();

        $columns = $this->connection->fetchFirstColumn('SHOW COLUMNS FROM `webhook_health`');

        static::assertEmpty(array_diff([
            'webhook_id',
            'endpoint_state',
            'consecutive_transient_failures',
            'consecutive_non_transient_failures',
            'degraded_cycle_count',
            'cooldown_until',
            'suspended_since',
            'disabled_since',
            'disabled_origin',
            'created_at',
            'updated_at',
        ], $columns));

        $eventLogColumns = $this->connection->fetchFirstColumn('SHOW COLUMNS FROM `webhook_event_log` LIKE \'failure_reason\'');
        static::assertContains('failure_reason', $eventLogColumns);

        $indexes = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => 'webhook_health']
        );

        static::assertContains('PRIMARY', $indexes);
        static::assertContains('idx.webhook_health.probe_due', $indexes);
        static::assertContains('idx.webhook_health.suspended_since', $indexes);

        static::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $webhookId]));

        $this->connection->delete('webhook', ['id' => $webhookId]);

        static::assertFalse($this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $webhookId]));
    }

    private function insertWebhook(int $active, int $errorCount): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('webhook', [
            'id' => $id,
            'name' => 'health-backfill-test-' . Uuid::randomHex(),
            'event_name' => 'test.event',
            'url' => 'https://example.com/webhook',
            'active' => $active,
            'error_count' => $errorCount,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        $this->createdWebhookIds[] = $id;

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchHealth(string $webhookId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT endpoint_state, consecutive_transient_failures, cooldown_until, disabled_since, disabled_origin
             FROM webhook_health WHERE webhook_id = :id',
            ['id' => $webhookId]
        );

        static::assertIsArray($row);

        return $row;
    }

    private function migrate(): void
    {
        (new Migration1783617451AddWebhookHealthModel())->update($this->connection);
    }
}
