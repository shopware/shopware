<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1783617451AddWebhookHealthModel extends MigrationStep
{
    use AddColumnTrait;

    public const DEFAULT_DEGRADED_THRESHOLD = 5;

    public function getCreationTimestamp(): int
    {
        return 1783617451;
    }

    public function update(Connection $connection): void
    {
        $this->createWebhookHealthTable($connection);
        $this->backfillFromWebhook($connection);

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
     * Existing rows are preserved; the duplicate-key clause handles a concurrent lazy insert.
     */
    private function backfillFromWebhook(Connection $connection): void
    {
        $degradedThreshold = self::DEFAULT_DEGRADED_THRESHOLD;

        $connection->executeStatement(
            'INSERT INTO `webhook_health`
                (`webhook_id`, `endpoint_state`, `consecutive_transient_failures`,
                 `cooldown_until`, `disabled_since`, `disabled_origin`, `created_at`)
             SELECT
                w.`id`,
                CASE
                    WHEN w.`active` = 0 THEN \'disabled\'
                    WHEN w.`error_count` >= :threshold THEN \'degraded\'
                    ELSE \'healthy\'
                END,
                w.`error_count`,
                CASE WHEN w.`active` = 1 AND w.`error_count` >= :threshold
                     THEN DATE_ADD(NOW(3), INTERVAL FLOOR(RAND() * 300) SECOND) END,
                CASE WHEN w.`active` = 0 THEN COALESCE(w.`updated_at`, w.`created_at`, NOW(3)) END,
                CASE WHEN w.`active` = 0 THEN \'escalation\' END,
                NOW(3)
             FROM `webhook` w
             LEFT JOIN `webhook_health` wh ON wh.`webhook_id` = w.`id`
             WHERE wh.`webhook_id` IS NULL
             ON DUPLICATE KEY UPDATE `webhook_id` = `webhook_health`.`webhook_id`',
            ['threshold' => $degradedThreshold]
        );
    }
}
