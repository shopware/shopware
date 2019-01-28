<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234597PaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234597;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
CREATE TABLE `payment_method` (
    `id`                   BINARY(16)                              NOT NULL,
    `technical_name`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `template`             VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `class`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `table`                VARCHAR(70) COLLATE utf8mb4_unicode_ci  NULL,
    `hide`                 TINYINT(1)                              NOT NULL DEFAULT \'0\',
    `percentage_surcharge` DOUBLE                                  NULL,
    `absolute_surcharge`   DOUBLE                                  NULL,
    `surcharge_string`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `position`             INT(11)                                 NOT NULL DEFAULT \'1\',
    `active`               TINYINT(1)                              NOT NULL DEFAULT \'0\',
    `allow_esd`            TINYINT(1)                              NOT NULL DEFAULT \'0\',
    `used_iframe`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `hide_prospect`        TINYINT(1)                              NOT NULL DEFAULT \'1\',
    `action`               VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `source`               INT(11)                                 NULL,
    `mobile_inactive`      TINYINT(1)                              NOT NULL DEFAULT \'0\',
    `risk_rules`           JSON                                    NULL,
    `plugin_id`            BINARY(16)                              NULL,
    `created_at`           DATETIME(3)                             NOT NULL,
    `updated_at`           DATETIME(3),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.name` (`technical_name`),
    CONSTRAINT `fk.payment_method.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `json.risk_rules` CHECK (JSON_VALID(`risk_rules`))
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
