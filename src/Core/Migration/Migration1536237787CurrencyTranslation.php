<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237787CurrencyTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237787;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `currency_translation` (
              `currency_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `short_name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`currency_id`, `language_id`),
              CONSTRAINT `fk.currency_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.currency_translation.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
