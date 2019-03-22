<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553507397UpdateProductConfigurator extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553507397;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate("
ALTER TABLE `product_configurator_setting`
ADD `position` int NOT NULL DEFAULT '0' AFTER `price`,
ADD `media_id` binary(16) NULL AFTER `position`,
DROP `prices`,
ADD FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;     
        ");
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
