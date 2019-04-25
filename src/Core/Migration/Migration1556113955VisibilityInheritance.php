<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1556113955VisibilityInheritance extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556113955;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product` ADD `visibilities` binary(16) NULL AFTER `prices`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
