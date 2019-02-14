<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550132687AddAttributeSetName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550132687;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
          ALTER TABLE `attribute_set`
          ADD COLUMN `name` VARCHAR(255) NULL AFTER `id`,
          ADD CONSTRAINT `uniq.attribute_set.name` UNIQUE  (`name`)
        ');

        $connection->exec('UPDATE `attribute_set` SET name = id');

        $connection->exec('
          ALTER TABLE `attribute_set`
          MODIFY COLUMN `name` VARCHAR(255) NOT NULL
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
