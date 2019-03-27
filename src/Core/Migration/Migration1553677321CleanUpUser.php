<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553677321CleanUpUser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553677321;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `user`
            DROP COLUMN `last_login`,
            DROP COLUMN `failed_logins`,
            DROP COLUMN `locked_until`
            ;'
        );
    }
}
