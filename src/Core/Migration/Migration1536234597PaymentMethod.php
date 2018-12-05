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
              `id` binary(16) NOT NULL,
              `technical_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `template` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `class` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `table` varchar(70) COLLATE utf8mb4_unicode_ci NULL,
              `hide` tinyint(1) NOT NULL DEFAULT \'0\',
              `percentage_surcharge` double NULL,
              `absolute_surcharge` double NULL,
              `surcharge_string` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `position` int(11) NOT NULL DEFAULT \'1\',
              `active` tinyint(1) NOT NULL DEFAULT \'0\',
              `allow_esd` tinyint(1) NOT NULL DEFAULT \'0\',
              `used_iframe` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `hide_prospect` tinyint(1) NOT NULL DEFAULT \'1\',
              `action` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `source` int(11) NULL,
              `mobile_inactive` tinyint(1) NOT NULL DEFAULT \'0\',
              `risk_rules` JSON NULL,
              `plugin_id` VARCHAR(250) NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq.name` (`technical_name`),
              CONSTRAINT `fk.payment_method.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `json.risk_rules` CHECK (JSON_VALID(`risk_rules`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
