<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232920PaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232920;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `payment_method` (
                `id`                    BINARY(16)                              NOT NULL,
                `handler_identifier`    VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT "' . str_replace('\\', '\\\\', DefaultPayment::class) . '",
                `position`              INT(11)                                 NOT NULL DEFAULT 1,
                `active`                TINYINT(1)                              NOT NULL DEFAULT 0,
                `availability_rule_id`  BINARY(16)                              NULL,
                `plugin_id`             BINARY(16)                              NULL,
                `media_id`              BINARY(16)                              NULL,
                `created_at`            DATETIME(3)                             NOT NULL,
                `updated_at`            DATETIME(3)                             NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.payment_method.availability_rule_id` FOREIGN KEY (`availability_rule_id`)
                  REFERENCES `rule` (id) ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT `fk.payment_method.plugin_id` FOREIGN KEY (`plugin_id`)
                  REFERENCES `plugin` (id) ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT `fk.payment_method.media_id` FOREIGN KEY (`media_id`)
                  REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `payment_method_translation` (
              `payment_method_id` BINARY(16)                              NOT NULL,
              `language_id`       BINARY(16)                              NOT NULL,
              `name`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `description`       MEDIUMTEXT COLLATE utf8mb4_unicode_ci   NULL,
              `custom_fields`     JSON                                    NULL,
              `created_at`        DATETIME(3)                             NOT NULL,
              `updated_at`        DATETIME(3)                             NULL,
              PRIMARY KEY (`payment_method_id`, `language_id`),
              CONSTRAINT `json.payment_method_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.payment_method_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.payment_method_translation.payment_method_id` FOREIGN KEY (`payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
