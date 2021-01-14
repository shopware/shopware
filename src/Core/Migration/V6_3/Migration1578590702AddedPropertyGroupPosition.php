<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1578590702AddedPropertyGroupPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578590702;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `property_group_translation` ADD `position` INT(11) NULL DEFAULT 1 AFTER `description`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
