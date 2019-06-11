<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1560264755AddedMissingIdColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1560264755;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
                ALTER TABLE `product_manufacturer_translation`
                ADD `id` binary(16) NOT NULL FIRST;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

