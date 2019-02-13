<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232870SearchDocument extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232870;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `search_document` (
              `id` BINARY(16) NOT NULL,
              `keyword` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `entity` VARCHAR(100) NOT NULL,
              `entity_id` BINARY(16) NOT NULL,
              `ranking` FLOAT NOT NULL,
              `attributes` JSON NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.entity_id` (`entity_id`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `uniq.language_keyword_entity_ranking` UNIQUE KEY (`language_id`, `keyword`, `entity`, `entity_id`, `ranking`),
              CONSTRAINT `fk.search_document.language_id` FOREIGN KEY (`language_id`) 
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
