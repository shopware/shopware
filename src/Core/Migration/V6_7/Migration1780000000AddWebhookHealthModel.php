<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * Webhook endpoint health (#16565): the whole health model in one migration — the
 * `webhook_health` table with its backfill, plus `webhook_event_log.failure_reason`.
 * `webhook_health` is internal and has no DAL definition (like webhook_delivery);
 * it is allow-listed in DefinitionValidator::TABLES_WITHOUT_DEFINITION.
 *
 * @internal
 */
#[Package('framework')]
class Migration1780000000AddWebhookHealthModel extends MigrationStep
{
    use AddColumnTrait;

    /**
     * The error_count boundary the backfill uses to seed DEGRADED. Frozen here as a
     * snapshot of the v6.8 default, so the migration never changes meaning.
     */
    public const DEFAULT_DEGRADED_THRESHOLD = 5;

    public function getCreationTimestamp(): int
    {
        return 1780000000;
    }

    public function update(Connection $connection): void
    {
        $this->createWebhookHealthTable($connection);
        $this->backfillFromWebhook($connection);

        // addColumn appends the column last with ALGORITHM=INSTANT (metadata only, no table
        // rebuild). Where INSTANT is unsupported it falls back to a plain ALTER.
        $this->addColumn($connection, 'webhook_event_log', 'failure_reason', 'VARCHAR(32)');
    }

    private function createWebhookHealthTable(Connection $connection): void
    {
        if (TableHelper::tableExists($connection, 'webhook_health')) {
            return;
        }

        $connection->executeStatement('
            CREATE TABLE `webhook_health` (
                `webhook_id`                     BINARY(16) NOT NULL,
                `endpoint_state`                 VARCHAR(20) NOT NULL DEFAULT \'healthy\',
                `consecutive_transient_failures` INT UNSIGNED NOT NULL DEFAULT 0,
                `consecutive_non_transient_failures` INT UNSIGNED NOT NULL DEFAULT 0,
                `degraded_cycle_count`           INT UNSIGNED NOT NULL DEFAULT 0,
                `cooldown_until`                 DATETIME(3) NULL,
                `suspended_since`                DATETIME(3) NULL,
                `disabled_since`                 DATETIME(3) NULL,
                `disabled_origin`                VARCHAR(16) NULL,
                `created_at`                     DATETIME(3) NOT NULL,
                `updated_at`                     DATETIME(3) NULL,
                PRIMARY KEY (`webhook_id`),
                KEY `idx.webhook_health.probe_due` (`endpoint_state`, `cooldown_until`),
                KEY `idx.webhook_health.suspended_since` (`endpoint_state`, `suspended_since`),
                CONSTRAINT `fk.webhook_health.webhook_id`
                    FOREIGN KEY (`webhook_id`) REFERENCES `webhook` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    /**
     * Creates one health row per existing webhook, derived from the legacy active /
     * error_count columns. Idempotent: INSERT IGNORE on the webhook_id primary key.
     *
     * `cooldown_until` is deliberately spread with RAND() across the first 5 minutes
     * (300 s), so the first probe tick after deployment does not hit every eligible
     * webhook at once. That value is intentionally random, not reproducible.
     *
     * Mapping: active=0 -> disabled; active=1 and error_count >= threshold -> degraded;
     * else healthy.
     *
     * Why active=0 becomes DISABLED, not SUSPENDED: a legacy inactive webhook is either a
     * deliberate off-switch (by an operator or the app) or a failure auto-disable — and
     * the auto-disable resets error_count to 0, so the two cases cannot be told apart
     * here. DISABLED keeps trunk's exact behaviour (no dispatching) and, unlike SUSPENDED,
     * never self-heals on traffic. So an endpoint that was deliberately turned off is not
     * silently reactivated at cutover; recovery is an app install/update or a manual
     * action. New failures after cutover still get the full DEGRADED -> SUSPENDED ->
     * recover path.
     *
     * Backfilled DISABLED rows get `disabled_origin = 'escalation'`: a pre-migration
     * operator disable cannot be told apart from a failure auto-disable, and `escalation`
     * keeps the app-update rescue path open — the pre-rework behaviour (ADR §Schema,
     * named trade-off).
     */
    private function backfillFromWebhook(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'webhook_health')) {
            return;
        }

        $degradedThreshold = self::DEFAULT_DEGRADED_THRESHOLD;

        $connection->executeStatement(
            'INSERT IGNORE INTO `webhook_health`
                (`webhook_id`, `endpoint_state`, `consecutive_transient_failures`,
                 `cooldown_until`, `disabled_since`, `disabled_origin`, `created_at`)
             SELECT
                `id`,
                CASE
                    WHEN `active` = 0 THEN \'disabled\'
                    WHEN `error_count` >= :threshold THEN \'degraded\'
                    ELSE \'healthy\'
                END,
                `error_count`,
                CASE WHEN `active` = 1 AND `error_count` >= :threshold
                     THEN DATE_ADD(NOW(3), INTERVAL FLOOR(RAND() * 300) SECOND) END,
                CASE WHEN `active` = 0 THEN COALESCE(`updated_at`, `created_at`, NOW(3)) END,
                CASE WHEN `active` = 0 THEN \'escalation\' END,
                NOW(3)
             FROM `webhook`',
            ['threshold' => $degradedThreshold]
        );
    }
}
