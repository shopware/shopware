<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1565007156RemoveAutoIncrement extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565007156;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE product DROP COLUMN auto_increment');
        $connection->executeUpdate('ALTER TABLE category DROP COLUMN auto_increment');
    }
}
