<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1714134471MyEntity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1714134471;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `my_entity` (
              `id` binary(16) NOT NULL,
              `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `price` json DEFAULT NULL,
              `product_id` binary(16) NOT NULL,
              `follow_id` binary(16) DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3) DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $connection->executeStatement('
            CREATE TABLE `my_entity_translation` (
                `my_entity_id` binary(16) NOT NULL,
                `language_id` binary(16) NOT NULL,
                `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                `position` int DEFAULT NULL,
                `weight` float DEFAULT NULL,
                `highlight` tinyint DEFAULT NULL,
                `release` datetime(3) DEFAULT NULL,
                `created_at` datetime(3) NOT NULL,
                `updated_at` datetime(3) DEFAULT NULL,
                PRIMARY KEY (`my_entity_id`, `language_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $connection->executeStatement('
            CREATE TABLE `my_sub` (
                `id` binary(16) NOT NULL,
                `my_entity_id` binary(16) NOT NULL,
                `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `created_at` datetime(3) NOT NULL,
                `updated_at` datetime(3) DEFAULT NULL,
                PRIMARY KEY (`my_entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $connection->executeStatement('
            CREATE TABLE `category_my_entity` (
                `my_entity_id` binary(16) NOT NULL,
                `category_id` binary(16) NOT NULL,
                PRIMARY KEY (`my_entity_id`, `category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }
}
