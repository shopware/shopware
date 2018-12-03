<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1543492512AddMediaType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543492512;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media`
            ADD `media_type` longtext DEFAULT NULL AFTER `file_name`;
        ');
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
    }
}
