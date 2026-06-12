<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1780000000AddWebhookHealthModel;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1780000000AddWebhookHealthModel::class)]
class Migration1780000000AddWebhookHealthModelTest extends TestCase
{
    private Connection $connection;

    /**
     * @var list<string> binary webhook ids created by the test, removed in tearDown
     */
    private array $createdWebhookIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        // webhook_health rows cascade on the FK to webhook.
        foreach ($this->createdWebhookIds as $id) {
            $this->connection->delete('webhook', ['id' => $id]);
        }
    }

    public function testBackfillMapsLegacyWebhookStateToEndpointState(): void
    {
        // active=0 preserves trunk's non-dispatching state → DISABLED (manual reactivation only),
        // never the recoverable SUSPENDED. active=0 is matched before the error_count check in the
        // CASE, so a disabled-but-failing webhook also stays DISABLED — neither is auto-reactivated
        // at cutover. Active webhooks map by error_count: at/above the threshold → DEGRADED, else HEALTHY.
        $inactive = $this->insertWebhook(active: 0, errorCount: 0);
        $inactiveFailing = $this->insertWebhook(active: 0, errorCount: Migration1780000000AddWebhookHealthModel::DEFAULT_DEGRADED_THRESHOLD);
        $activeFailing = $this->insertWebhook(active: 1, errorCount: Migration1780000000AddWebhookHealthModel::DEFAULT_DEGRADED_THRESHOLD);
        $healthy = $this->insertWebhook(active: 1, errorCount: 0);

        $this->migrate();

        $disabled = $this->fetchHealth($inactive);
        static::assertSame('disabled', $disabled['endpoint_state'], 'inactive → disabled');
        static::assertNotNull($disabled['disabled_since']);
        // A pre-migration operator disable is indistinguishable from a failure auto-disable, so the
        // backfill seeds 'escalation' for all of them — the app-update rescue path stays open (ADR §Schema).
        static::assertSame('escalation', $disabled['disabled_origin']);
        static::assertNull($disabled['suspended_since']);
        static::assertNull($disabled['cooldown_until']);

        static::assertSame('disabled', $this->fetchHealth($inactiveFailing)['endpoint_state'], 'inactive+failing → disabled (active=0 outranks error_count)');

        $degraded = $this->fetchHealth($activeFailing);
        static::assertSame('degraded', $degraded['endpoint_state'], 'active+failing → degraded');
        static::assertSame(Migration1780000000AddWebhookHealthModel::DEFAULT_DEGRADED_THRESHOLD, (int) $degraded['consecutive_transient_failures']);
        static::assertSame(0, (int) $degraded['consecutive_non_transient_failures']);
        static::assertNotNull($degraded['cooldown_until']);
        static::assertNull($degraded['suspended_since']);
        static::assertNull($degraded['disabled_origin']);

        $ok = $this->fetchHealth($healthy);
        static::assertSame('healthy', $ok['endpoint_state'], 'active+no errors → healthy');
        static::assertNull($ok['cooldown_until']);
        static::assertNull($ok['suspended_since']);
        static::assertNull($ok['disabled_origin']);
    }

    public function testBackfillIsIdempotent(): void
    {
        $id = $this->insertWebhook(active: 0, errorCount: 0);

        $this->migrate();
        $first = $this->fetchHealth($id);

        $this->migrate();
        $second = $this->fetchHealth($id);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_health WHERE webhook_id = :id',
            ['id' => $id]
        ));
        static::assertSame($first['endpoint_state'], $second['endpoint_state']);
        static::assertSame($first['disabled_since'], $second['disabled_since']);
    }

    public function testWebhookHealthSchemaMatchesAdr(): void
    {
        $this->migrate();

        $columns = $this->connection->fetchFirstColumn('SHOW COLUMNS FROM `webhook_health`');

        // The ADR's eight fields (§Schema and APIs) plus the PK and timestamps — nothing else.
        static::assertEqualsCanonicalizing([
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
        ], $columns);
    }

    public function testFailureReasonColumnExistsAndNoAuditTableIsCreated(): void
    {
        $this->migrate();

        $columns = $this->connection->fetchFirstColumn('SHOW COLUMNS FROM `webhook_event_log` LIKE \'failure_reason\'');
        static::assertContains('failure_reason', $columns);

        // Provenance rides the lifecycle events and structured logs — there is no audit table (ADR §Schema).
        static::assertFalse($this->connection->fetchOne('SHOW TABLES LIKE \'webhook_reactivation_log\''));
    }

    public function testWebhookHealthIndexesExist(): void
    {
        $this->migrate();

        $indexes = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => 'webhook_health']
        );

        static::assertContains('idx.webhook_health.probe_due', $indexes);
        static::assertContains('idx.webhook_health.suspended_since', $indexes);
    }

    public function testDeletingWebhookCascadesHealthRow(): void
    {
        $id = $this->insertWebhook(active: 1, errorCount: 0);
        $this->migrate();

        static::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $id]));

        $this->connection->delete('webhook', ['id' => $id]);

        static::assertFalse($this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $id]));
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
            'SELECT endpoint_state, consecutive_transient_failures, consecutive_non_transient_failures,
                    cooldown_until, suspended_since, disabled_since, disabled_origin
             FROM webhook_health WHERE webhook_id = :id',
            ['id' => $webhookId]
        );

        static::assertIsArray($row);

        return $row;
    }

    private function migrate(): void
    {
        (new Migration1780000000AddWebhookHealthModel())->update($this->connection);
    }
}
