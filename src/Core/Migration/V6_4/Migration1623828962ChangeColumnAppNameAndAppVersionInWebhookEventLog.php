<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1623828962ChangeColumnAppNameAndAppVersionInWebhookEventLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1623828962;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `webhook_event_log`
                MODIFY COLUMN `app_name` VARCHAR(255) NULL,
                MODIFY COLUMN `app_version` VARCHAR(255) NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
