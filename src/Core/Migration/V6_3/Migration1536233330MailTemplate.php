<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233330MailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233330;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `mail_template_type` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `available_entities` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `uniq.mail_template_type.technical_name` UNIQUE (`technical_name`),
              CONSTRAINT `json.mail_template_type.available_entities` CHECK (JSON_VALID(`available_entities`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $connection->executeStatement('
            CREATE TABLE `mail_template_type_translation` (
              `mail_template_type_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`mail_template_type_id`, `language_id`),
              CONSTRAINT `json.mail_template_type_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.mail_template_type_translation.mail_template_type_id` FOREIGN KEY (`mail_template_type_id`)
                REFERENCES `mail_template_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.mail_template_type_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $connection->executeStatement('
            CREATE TABLE `mail_template` (
              `id` BINARY(16) NOT NULL,
              `mail_template_type_id` BINARY(16) NULL,
              `system_default` TINYINT(1) unsigned NOT NULL DEFAULT \'0\',
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3),
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.mail_template.mail_template_type_id` FOREIGN KEY (`mail_template_type_id`)
                REFERENCES `mail_template_type` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `mail_template_translation` (
              `mail_template_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `sender_name` VARCHAR(255) DEFAULT NULL,
              `subject` VARCHAR(255) DEFAULT NULL,
              `description` LONGTEXT DEFAULT NULL,
              `content_html` LONGTEXT DEFAULT NULL,
              `content_plain` LONGTEXT DEFAULT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) DEFAULT NULL,
              PRIMARY KEY (`mail_template_id`, `language_id`),
              CONSTRAINT `fk.mail_template_translation.mail_template_id` FOREIGN KEY (`mail_template_id`)
                REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.mail_template_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `mail_template_sales_channel` (
              `id` BINARY(16) NOT NULL,
              `mail_template_id` BINARY(16) NOT NULL,
              `mail_template_type_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.mail_template_id__sales_channel_id` (`mail_template_id`, `sales_channel_id`),
              CONSTRAINT `fk.mail_template_sales_channel.mail_template_id`
              FOREIGN KEY (mail_template_id) REFERENCES `mail_template` (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.mail_template_sales_channel.mail_template_type_id`
              FOREIGN KEY (mail_template_type_id) REFERENCES `mail_template_type` (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.mail_template_sales_channel.sales_channel_id`
              FOREIGN KEY (sales_channel_id) REFERENCES `sales_channel` (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
