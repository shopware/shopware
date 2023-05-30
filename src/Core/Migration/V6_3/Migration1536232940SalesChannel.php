<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232940SalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232940;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `sales_channel` (
              `id` BINARY(16) NOT NULL,
              `type_id` BINARY(16) NOT NULL,
              `short_name` VARCHAR(45) NULL,
              `configuration` JSON NULL,
              `access_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `currency_id` BINARY(16) NOT NULL,
              `payment_method_id` BINARY(16) NOT NULL,
              `shipping_method_id` BINARY(16) NOT NULL,
              `country_id` BINARY(16) NOT NULL,
              `navigation_category_id` BINARY(16) NOT NULL,
              `navigation_category_version_id` BINARY(16) NOT NULL,
              `footer_category_id` BINARY(16) NULL,
              `footer_category_version_id` BINARY(16) NULL,
              `service_category_id` BINARY(16) NULL,
              `service_category_version_id` BINARY(16) NULL,
              `active` TINYINT(1) NOT NULL DEFAULT '1',
              `navigation_id` BINARY(16) NULL,
              `navigation_version_id` BINARY(16),
              `customer_group_id` BINARY(16) NOT NULL,
              `mail_header_footer_id` BINARY(16) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.access_key` (`access_key`),
              CONSTRAINT `json.sales_channel.configuration` CHECK (JSON_VALID(`configuration`)),
              CONSTRAINT `fk.sales_channel.country_id` FOREIGN KEY (`country_id`)
                REFERENCES `country` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.currency_id` FOREIGN KEY (`currency_id`)
                REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.payment_method_id` FOREIGN KEY (`payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.type_id` FOREIGN KEY (`type_id`)
                REFERENCES `sales_channel_type` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.navigation_category_id` FOREIGN KEY (`navigation_category_id`, `navigation_category_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.footer_category_id` FOREIGN KEY (`footer_category_id`, `footer_category_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.service_category_id` FOREIGN KEY (`service_category_id`, `service_category_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.navigation_id` FOREIGN KEY (`navigation_id`, `navigation_version_id`)
                REFERENCES `navigation` (`id`, `version_id`) ON DELETE NO ACTION ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.customer_group_id` FOREIGN KEY (`customer_group_id`)
                REFERENCES `customer_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.id` FOREIGN KEY (`mail_header_footer_id`)
                REFERENCES `mail_header_footer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);

        $connection->executeStatement('
            CREATE TABLE `sales_channel_translation` (
              `sales_channel_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`sales_channel_id`, `language_id`),
              CONSTRAINT `json.sales_channel_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.sales_channel_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel_translation.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `sales_channel_language` (
              `sales_channel_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`sales_channel_id`, `language_id`),
              CONSTRAINT `fk.sales_channel_language.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel_language.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `sales_channel_currency` (
              `sales_channel_id` BINARY(16) NOT NULL,
              `currency_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`sales_channel_id`, `currency_id`),
              CONSTRAINT `fk.sales_channel_currency.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel_currency.currency_id` FOREIGN KEY (`currency_id`)
                REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `sales_channel_country` (
              `sales_channel_id` BINARY(16) NOT NULL,
              `country_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`sales_channel_id`, `country_id`),
              CONSTRAINT `fk.sales_channel_country.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel_country.country_id` FOREIGN KEY (`country_id`)
                REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `sales_channel_shipping_method` (
              `sales_channel_id` BINARY(16) NOT NULL,
              `shipping_method_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`sales_channel_id`, `shipping_method_id`),
              CONSTRAINT `fk.sales_channel_shipping_method.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel_shipping_method.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `sales_channel_payment_method` (
              `sales_channel_id` BINARY(16) NOT NULL,
              `payment_method_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`sales_channel_id`, `payment_method_id`),
              CONSTRAINT `fk.sales_channel_payment_method.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel_payment_method.payment_method_id` FOREIGN KEY (`payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
