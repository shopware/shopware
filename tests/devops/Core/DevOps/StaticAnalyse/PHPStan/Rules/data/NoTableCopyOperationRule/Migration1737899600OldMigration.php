<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1737899600OldMigration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1737899600;
    }

    public function update(Connection $connection): void
    {
        // This is before cutoff date (1737899680) - should not be caught
        $connection->executeStatement('
            ALTER TABLE `product`
            ADD COLUMN `states` JSON NULL,
            ADD CONSTRAINT `json.product.states` CHECK (JSON_VALID(`states`))
        ');
    }
}
