<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553613095RemoveProductService extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553613095;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `product` DROP COLUMN `services`;'
        );

        $connection->exec(
            'DROP TABLE `product_service`;'
        );
    }
}
