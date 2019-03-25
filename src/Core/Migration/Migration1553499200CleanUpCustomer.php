<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553499200CleanUpCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553499200;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `customer`
            DROP INDEX `idx.validation`,
            DROP COLUMN `encoder`,
            DROP COLUMN `internal_comment`,
            DROP COLUMN `validation`,
            DROP COLUMN `affiliate`,
            DROP COLUMN `referer`,
            DROP COLUMN `failed_logins`,
            DROP COLUMN `locked_until`,
            DROP COLUMN `session_id`,
            DROP COLUMN `confirmation_key`
            ;'
        );
    }
}
