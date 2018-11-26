<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237795ListingFacetTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237795;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `listing_facet_translation` (
              `listing_facet_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`listing_facet_id`, `language_id`),
              CONSTRAINT `fk.listing_facet_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.listing_facet_translation.listing_facet_id` FOREIGN KEY (`listing_facet_id`) REFERENCES `listing_facet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
