<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232950Customer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232950;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE `customer` (
              `id` BINARY(16) NOT NULL,
              `auto_increment` BIGINT unsigned NOT NULL AUTO_INCREMENT,
              `customer_group_id` BINARY(16) NOT NULL,
              `default_payment_method_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `last_payment_method_id` BINARY(16) NULL,
              `default_billing_address_id` BINARY(16) NOT NULL,
              `default_shipping_address_id` BINARY(16) NOT NULL,
              `customer_number` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL,
              `salutation` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL,
              `first_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `last_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `password` VARCHAR(1024) COLLATE utf8mb4_unicode_ci NULL,
              `email` VARCHAR(254) COLLATE utf8mb4_unicode_ci NOT NULL,
              `title` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
              `encoder` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'md5',
              `active` TINYINT(1) NOT NULL DEFAULT 1,
              `guest` TINYINT(1) NOT NULL DEFAULT 0,
              `confirmation_key` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
              `first_login` DATE NULL,
              `last_login` DATETIME(3) NULL,
              `session_id` VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL,
              `newsletter` TINYINT(1) NOT NULL DEFAULT '0',
              `validation` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT '',
              `affiliate` TINYINT(1) NULL,
              `referer` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `internal_comment` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `failed_logins` INT(11) NOT NULL DEFAULT 0,
              `locked_until` DATETIME(3) NULL,
              `birthday` DATE NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.auto_increment` (`auto_increment`),
              KEY `idx.sessionID` (`session_id`),
              KEY `idx.firstlogin` (`first_login`),
              KEY `idx.lastlogin` (`last_login`),
              KEY `idx.validation` (`validation`),
              KEY `idx.customer.default_billing_address_id` (`default_billing_address_id`),
              KEY `idx.customer.default_shipping_address_id` (`default_shipping_address_id`),
              CONSTRAINT `check.password` CHECK(`password` IS NOT NULL OR `guest` = 1),
              CONSTRAINT `JSON.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.customer.customer_group_id` FOREIGN KEY (`customer_group_id`)
                REFERENCES `customer_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.customer.default_payment_method_id` FOREIGN KEY (`default_payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.customer.last_payment_method_id` FOREIGN KEY (`last_payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.customer.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
