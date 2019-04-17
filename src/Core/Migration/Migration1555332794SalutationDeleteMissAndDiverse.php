<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1555332794SalutationDeleteMissAndDiverse extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555332794;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery("
            DELETE FROM salutation
            WHERE `salutation_key` IN ('diverse', 'miss');
        ");
    }
}
