<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1589289150ChangeSalutationIdNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1589289150;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $connection->executeUpdate(
            ' ALTER TABLE customer_address
                    MODIFY salutation_id binary(16) NULL;
                    ALTER TABLE customer
                    MODIFY salutation_id binary(16) NULL;
                    ALTER TABLE order_customer
                    MODIFY salutation_id binary(16) NULL;
                    ALTER TABLE order_address
                    MODIFY salutation_id binary(16) NULL;
            '
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
