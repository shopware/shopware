<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1696321447AddOnlyLiveVersionToWebhookAndWebhookEventLogTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1696321447;
    }

    public function update(Connection $connection): void
    {
        $this->addToWebhookTable($connection);
        $this->addToWebhookEventLogTable($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function addToWebhookTable(Connection $connection): void
    {
        if ($this->columnExists($connection, 'webhook', 'only_live_version')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `webhook` ADD `only_live_version` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;');
    }

    private function addToWebhookEventLogTable(Connection $connection): void
    {
        if ($this->columnExists($connection, 'webhook_event_log', 'only_live_version')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `webhook_event_log` ADD `only_live_version` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;');
    }
}
