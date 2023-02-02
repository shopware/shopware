<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
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
