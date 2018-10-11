<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536850297IncreaseMimeTypeColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536850297;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media`
            MODIFY COLUMN `mime_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
