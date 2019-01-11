<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536240664ProductTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536240664;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_translation` (
              `product_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `additional_text` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `keywords` mediumtext COLLATE utf8mb4_unicode_ci NULL,
              `description` mediumtext COLLATE utf8mb4_unicode_ci NULL,
              `description_long` mediumtext COLLATE utf8mb4_unicode_ci NULL,
              `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `pack_unit` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`product_id`, `language_id`, `product_version_id`),
              CONSTRAINT `fk.product_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_translation.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
