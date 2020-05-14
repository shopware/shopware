<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1589446209AddAcceptAllCookieFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1589446209;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'ALTER TABLE sales_channel
            ADD COLUMN `cookie_accept_all_active` TINYINT(1) NOT NULL DEFAULT 0 AFTER `analytics_id`'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
        $connection->executeUpdate(
            'ALTER TABLE sales_channel
            DROP COLUMN `cookie_accept_all_active`'
        );
    }
}
