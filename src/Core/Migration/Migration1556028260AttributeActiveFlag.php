<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1556028260AttributeActiveFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556028260;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `attribute`
            ADD `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `config`'
        );

        $connection->exec('
            ALTER TABLE `attribute_set`
            ADD `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `config`'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
