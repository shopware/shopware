<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1591817370AddCustomerTagsManyToManyIdField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591817370;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'ALTER TABLE `customer`
            ADD COLUMN `tag_ids` JSON NULL,
            ADD CONSTRAINT `json.customer.tag_ids` CHECK (JSON_VALID(`tag_ids`));'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
