<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1556789297DropStoreSettings extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556789297;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('DROP TABLE `store_settings`');
    }
}
