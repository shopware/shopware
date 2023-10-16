<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1697462064 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697462064;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery("UPDATE media SET path = NULL WHERE file_name IS NULL OR file_name = ''");
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
