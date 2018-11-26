<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237791Customer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237791;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE `customer` (
              `id` binary(16) NOT NULL,
              `auto_increment` bigint unsigned NOT NULL AUTO_INCREMENT,
              `customer_group_id` binary(16) NOT NULL,
              `default_payment_method_id` binary(16) NOT NULL,
              `sales_channel_id` binary(16) NOT NULL,
              `last_payment_method_id` binary(16) DEFAULT NULL,
              `default_billing_address_id` binary(16) NOT NULL,
              `default_shipping_address_id` binary(16) NOT NULL,
              `customer_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
              `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NULL,
              `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `password` varchar(1024) COLLATE utf8mb4_unicode_ci NULL,
              `email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
              `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `encoder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'md5',
              `active` tinyint(1) NOT NULL DEFAULT '1',
              `guest` tinyint(1) NOT NULL DEFAULT '0',
              `confirmation_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `first_login` date DEFAULT NULL,
              `last_login` datetime(3) DEFAULT NULL,
              `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `newsletter` tinyint(1) NOT NULL DEFAULT '0',
              `validation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
              `affiliate` tinyint(1) DEFAULT NULL,
              `referer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `internal_comment` mediumtext COLLATE utf8mb4_unicode_ci,
              `failed_logins` int(11) NOT NULL DEFAULT '0',
              `locked_until` datetime(3) DEFAULT NULL,
              `birthday` date DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              UNIQUE `uniq.auto_increment` (`auto_increment`),
              CHECK(`password` IS NOT NULL OR `guest` = 1),
              KEY `sessionID` (`session_id`),
              KEY `firstlogin` (`first_login`),
              KEY `lastlogin` (`last_login`),
              KEY `validation` (`validation`),
              KEY `fk.customer.default_billing_address_id` (`default_billing_address_id`),
              KEY `fk.customer.default_shipping_address_id` (`default_shipping_address_id`),
              CONSTRAINT `fk.customer.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.customer.default_payment_method_id` FOREIGN KEY (`default_payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.customer.last_payment_method_id` FOREIGN KEY (`last_payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.customer.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
