<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1620146632AddActiveAndErrorCountIntoWebhook extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620146632;
    }

    public function update(Connection $connection): void
    {
        $activeColumn = $connection->fetchOne(
            'SHOW COLUMNS FROM `webhook` WHERE `Field` LIKE :column;',
            ['column' => 'active']
        );

        if ($activeColumn === false) {
            $connection->executeStatement('ALTER TABLE `webhook` ADD COLUMN `active` TINYINT(1) DEFAULT 1 AFTER `app_id`');
        }

        $errorCount = $connection->fetchOne(
            'SHOW COLUMNS FROM `webhook` WHERE `Field` LIKE :column;',
            ['column' => 'error_count']
        );

        if ($errorCount === false) {
            $connection->executeStatement('ALTER TABLE `webhook` ADD COLUMN `error_count` INT(11) NOT NULL DEFAULT 0');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
