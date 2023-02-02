<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1629877210UpdateWebhookNameAndSerializedWebhookMessageOfWebhookEventLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1629877210;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `webhook_event_log` MODIFY COLUMN `webhook_name` TEXT NOT NULL;');
        $connection->executeStatement('ALTER TABLE `webhook_event_log` MODIFY COLUMN `serialized_webhook_message` LONGBLOB NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
