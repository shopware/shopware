<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769435680OldMigration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769435679;
    }

    public function update(Connection $connection): void
    {
        // This is before cutoff date - should not be caught
        $connection->executeStatement('
            ALTER TABLE `product`
            ADD COLUMN `states` JSON NULL,
            ADD CONSTRAINT `json.product.states` CHECK (JSON_VALID(`states`))
        ');
    }
}
