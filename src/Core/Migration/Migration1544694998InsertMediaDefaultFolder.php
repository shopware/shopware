<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1544694998InsertMediaDefaultFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1544694998;
    }

    public function update(Connection $connection): void
    {
        $productId = Uuid::uuid4()->getBytes();
        $manufacturerId = Uuid::uuid4()->getBytes();

        $connection->executeQuery('
            INSERT INTO `media_default_folder` 
                (`id`, `associations`, `entity`, `created_at`)
            VALUES 
                (?, \'["productMedia"]\', "product", NOW()), 
                (?, \'["productManufacturers"]\', "product_manufacturer", NOW())
        ', [$productId, $manufacturerId]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
