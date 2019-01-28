<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548668128ProductStreamTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548668128;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_stream_translation` (
              `product_stream_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `description` mediumtext COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`product_stream_id`, `language_id`),
              CONSTRAINT `fk.product_stream_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_stream_translation.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
