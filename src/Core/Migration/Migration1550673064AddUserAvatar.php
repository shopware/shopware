<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550673064AddUserAvatar extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550673064;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `user`
             ADD COLUMN `avatar_id` BINARY(16) NULL AFTER active,
             ADD CONSTRAINT `fk.user.avatar_id`
                 FOREIGN KEY (avatar_id) REFERENCES `media` (id) ON DELETE SET NULL;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
