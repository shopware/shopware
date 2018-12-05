<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1543492749ThumbnailSize extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543492749;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_thumbnail_size`(
              `id` BINARY(16),
              `width` int(11),
              `height` int(11),
              PRIMARY KEY (`id`),
              CONSTRAINT `uniq.width` UNIQUE (`width`, `height`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
