<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232860ShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232860;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `shipping_method` (
              `id`                      BINARY(16)          NOT NULL,
              `active`                  TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `availability_rule_id`    BINARY(16)          NOT NULL,
              `media_id`                BINARY(16)          NULL,
              `delivery_time_id`        BINARY(16)          NOT NULL,
              `created_at`              DATETIME(3)         NOT NULL,
              `updated_at`              DATETIME(3)         NULL,
               PRIMARY KEY (`id`),
               CONSTRAINT `fk.shipping_method.media_id` FOREIGN KEY (media_id)
                 REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
               CONSTRAINT `fk.shipping_method.availability_rule_id` FOREIGN KEY (`availability_rule_id`)
                 REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk.shipping_method.delivery_time_id`
                FOREIGN KEY (`delivery_time_id`) REFERENCES `delivery_time` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `shipping_method_translation` (
              `shipping_method_id`  BINARY(16)                                  NOT NULL,
              `language_id`         BINARY(16)                                  NOT NULL,
              `name`                VARCHAR(255)    COLLATE utf8mb4_unicode_ci  NULL,
              `description`         MEDIUMTEXT      COLLATE utf8mb4_unicode_ci  NULL,
              `custom_fields`       JSON                                        NULL,
              `created_at`          DATETIME(3)                                 NOT NULL,
              `updated_at`          DATETIME(3)                                 NULL,
              PRIMARY KEY (`shipping_method_id`, `language_id`),
              CONSTRAINT `json.shipping_method_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.shipping_method_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.shipping_method_translation.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
