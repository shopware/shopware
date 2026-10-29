<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1792500758AddWebhookIdToWebhookEventLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792500758;
    }

    public function update(Connection $connection): void
    {
        // Indexed because the event log is filtered by webhook_id for every caller who isn't an admin.
        // Set to NULL when the webhook is deleted: the log entries are kept, but a webhook recreated with the same id can't read them.
        // Foreign key checks are off while the constraint is added, so the database doesn't copy and lock the log table to validate it.
        // That can't let invalid data in: the column was just added, so every existing webhook_id is NULL and no row can violate the constraint.
        $this->addColumn($connection, 'webhook_event_log', 'webhook_id', 'BINARY(16)');

        $changes = [];
        if (!TableHelper::indexExists($connection, 'webhook_event_log', 'idx.webhook_event_log.webhook_id')) {
            $changes[] = 'ADD INDEX `idx.webhook_event_log.webhook_id` (`webhook_id`)';
        }
        if (!TableHelper::foreignKeyExists($connection, 'webhook_event_log', 'fk.webhook_event_log.webhook_id')) {
            $changes[] = 'ADD CONSTRAINT `fk.webhook_event_log.webhook_id` FOREIGN KEY (`webhook_id`) REFERENCES `webhook` (`id`) ON DELETE SET NULL ON UPDATE CASCADE';
        }

        if ($changes === []) {
            return;
        }

        $foreignKeyChecks = $connection->fetchOne('SELECT @@FOREIGN_KEY_CHECKS');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $connection->executeStatement('ALTER TABLE `webhook_event_log` ' . implode(', ', $changes));
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = ' . $foreignKeyChecks);
        }
    }
}
