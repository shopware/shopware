<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234596SearchDocument extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234596;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `search_document` (
              `id` binary(16) NOT NULL,
              `keyword` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
              `language_id` binary(16) NOT NULL,
              `entity` varchar(100) NOT NULL,
              `entity_id` binary(16) NOT NULL,
              `ranking` float NOT NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.search_document.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              UNIQUE KEY `uniq.language_keyword_entity_ranking` (`language_id`, `keyword`, `entity`, `entity_id`, `ranking`),
              INDEX `idx.entity_id` (`entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
