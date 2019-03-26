<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553527278CleanUpManufacturer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553527278;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `product_manufacturer_translation`
            DROP COLUMN `meta_title`,
            DROP COLUMN `meta_description`,
            DROP COLUMN `meta_keywords`
            ;'
        );
    }
}
