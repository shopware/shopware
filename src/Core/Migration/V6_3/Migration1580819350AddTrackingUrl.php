<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1580819350AddTrackingUrl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580819350;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `shipping_method_translation` ADD `tracking_url` MEDIUMTEXT NULL DEFAULT NULL AFTER `description`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
