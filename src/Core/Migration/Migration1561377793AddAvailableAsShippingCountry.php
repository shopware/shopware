<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1561377793AddAvailableAsShippingCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561377793;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `country` ADD `shipping_available` TinyInt(1) NOT NULL DEFAULT 1;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
