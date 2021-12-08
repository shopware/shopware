<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1639416812AddNormalizedColumnToWebhookTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1639416812;
    }

    public function update(Connection $connection): void
    {
        $normalizedColumn = $connection->fetchColumn(
            'SHOW COLUMNS FROM `webhook` WHERE `Field` LIKE :column;',
            ['column' => 'normalized']
        );

        if ($normalizedColumn === false) {
            $connection->executeUpdate('ALTER TABLE `webhook` ADD COLUMN `normalized` TINYINT(1) DEFAULT 0 AFTER `active`');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
