<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Webhook endpoint health (#16565): widens `idx.webhook_delivery.webhook_status` from
 * `(webhook_id, delivery_status)` to `(webhook_id, delivery_status, id)`. With the extra
 * column, the DEGRADED probe's oldest-held-row lookup
 * (`WHERE webhook_id=? AND delivery_status='paused' ORDER BY id LIMIT 1`) and the
 * webhook-scoped pause/drop/resume flips run fully from the index instead of filesorting
 * the matched rows. Index-only change (`ALGORITHM=INPLACE`). The receiver's
 * `idx.webhook_delivery.partition_status_retry` is untouched.
 *
 * @internal
 */
#[Package('framework')]
class Migration1780000100WidenWebhookDeliveryWebhookStatusIndex extends MigrationStep
{
    private const INDEX = 'idx.webhook_delivery.webhook_status';

    public function getCreationTimestamp(): int
    {
        return 1780000100;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index
             ORDER BY SEQ_IN_INDEX',
            ['table' => 'webhook_delivery', 'index' => self::INDEX]
        );

        if ($columns === ['webhook_id', 'delivery_status', 'id']) {
            return;
        }

        // This index is the child-side covering index for fk.webhook_delivery.webhook_id, so it
        // must never be missing — InnoDB can reject a bare DROP. When the index already exists,
        // DROP and re-ADD it in a SINGLE statement: InnoDB validates the FK covering requirement
        // against the statement's final shape, which still starts with webhook_id. Index-only
        // change, so ALGORITHM=INPLACE.
        if ($columns === []) {
            $connection->executeStatement(
                'ALTER TABLE `webhook_delivery`
                    ADD INDEX `idx.webhook_delivery.webhook_status` (`webhook_id`, `delivery_status`, `id`),
                    ALGORITHM=INPLACE, LOCK=NONE'
            );

            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `webhook_delivery`
                DROP INDEX `idx.webhook_delivery.webhook_status`,
                ADD INDEX `idx.webhook_delivery.webhook_status` (`webhook_id`, `delivery_status`, `id`),
                ALGORITHM=INPLACE, LOCK=NONE'
        );
    }
}
