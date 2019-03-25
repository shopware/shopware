<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552568830AddUserStoreToken extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552568830;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
             ALTER TABLE `user`
             ADD COLUMN `store_token` VARCHAR(255) NULL AFTER `locale_id`;
         ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
