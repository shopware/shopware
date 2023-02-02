<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1554900301AddReviewTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554900301;
    }

    public function update(Connection $connection): void
    {
        // implement update

        $connection->executeStatement('
            DROP TABLE IF EXISTS `product_review`;
        ');
        $connection->executeStatement('
            CREATE TABLE `product_review` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NULL,
                `sales_channel_id` BINARY(16) NULL,
                `language_id` BINARY(16) NULL,
                `external_user` VARCHAR(255) NULL,
                `external_email` VARCHAR(255) NULL,
                `title` VARCHAR(255) NULL,
                `content` LONGTEXT NULL,
                `points` DOUBLE NULL,
                `status` TINYINT(1) NULL DEFAULT \'0\',
                `comment` LONGTEXT NULL,
                `updated_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `fk.product_review.product_id` (`product_id`,`product_version_id`),
                KEY `fk.product_review.customer_id` (`customer_id`),
                KEY `fk.product_review.sales_channel_id` (`sales_channel_id`),
                KEY `fk.product_review.language_id` (`language_id`),
                CONSTRAINT `fk.product_review.product_id` FOREIGN KEY (`product_id`,`product_version_id`) REFERENCES `product` (`id`,`version_id`)  ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_review.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`),
                CONSTRAINT `fk.product_review.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`),
                CONSTRAINT `fk.product_review.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
