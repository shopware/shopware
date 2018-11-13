<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1540989280UpdateCartPrimaryKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1540989280;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE cart DROP PRIMARY KEY,
            ADD PRIMARY KEY (token, tenant_id);
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
