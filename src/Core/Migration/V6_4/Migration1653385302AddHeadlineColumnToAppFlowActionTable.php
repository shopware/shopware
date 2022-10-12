<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1653385302AddHeadlineColumnToAppFlowActionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1653385302;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'app_flow_action_translation', 'headline')) {
            $connection->executeStatement('ALTER TABLE `app_flow_action_translation` ADD `headline` VARCHAR(255) NULL AFTER `description`;');
        }

        $connection->executeStatement('
            ALTER TABLE `app_flow_action_translation`
                MODIFY COLUMN `description` LONGTEXT NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
