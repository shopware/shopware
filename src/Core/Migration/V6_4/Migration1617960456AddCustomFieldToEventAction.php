<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1617960456AddCustomFieldToEventAction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617960456;
    }

    public function update(Connection $connection): void
    {
        $featureColumn = $connection->fetchColumn(
            'SHOW COLUMNS FROM `event_action` WHERE `Field` LIKE :column;',
            ['column' => 'custom_fields']
        );

        if ($featureColumn === false) {
            $connection->executeUpdate(
                'ALTER TABLE `event_action`
                ADD COLUMN `custom_fields` JSON NULL AFTER `config`,
                ADD CONSTRAINT `json.event_action.custom_fields` CHECK (JSON_VALID(`custom_fields`));'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
