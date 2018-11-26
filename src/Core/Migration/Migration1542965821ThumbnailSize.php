<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542965821ThumbnailSize extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542965821;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `thumbnail_size`(
              `id` BINARY(16),
              `width` int(11),
              `height` int(11),
              PRIMARY KEY (`id`),
              CONSTRAINT `thumbnail_size_width_height_uk` UNIQUE (`width`, `height`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
