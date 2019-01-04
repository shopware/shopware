<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1545203945AddPropertyDescription extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1545203945;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            "ALTER TABLE `configuration_group` 
            ADD `sorting_type` varchar(50) NOT NULL DEFAULT 'alphanumeric' AFTER `id`,
            ADD `display_type` varchar(50) NOT NULL DEFAULT 'text' AFTER `sorting_type`"
        );

        $connection->executeUpdate("ALTER TABLE `configuration_group_translation` ADD `description` longtext NULL AFTER `name`");

        $connection->executeUpdate("ALTER TABLE `configuration_group_option_translation` ADD `position` int(11) NOT NULL DEFAULT '1' AFTER `name`;");
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
