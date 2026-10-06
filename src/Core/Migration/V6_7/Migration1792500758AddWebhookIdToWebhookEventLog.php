<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

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
        $columnAdded = $this->addColumn($connection, 'webhook_event_log', 'webhook_id', 'BINARY(16)');
        if ($columnAdded) {
            $connection->executeStatement('ALTER TABLE `webhook_event_log` ADD INDEX `idx.webhook_event_log.webhook_id` (`webhook_id`)');
        }
    }
}
