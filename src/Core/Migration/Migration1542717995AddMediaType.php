<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542717995AddMediaType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542717995;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media`
            ADD `media_type` longtext DEFAULT NULL;
        ');
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
    }
}
