<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1569403146ProductVisibilityUnique extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1569403146;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `product_visibility`
                ADD UNIQUE KEY `uniq.product_id__sales_channel_id` (`product_id`, `product_version_id`, `sales_channel_id`)

        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
