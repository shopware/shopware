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
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `technical_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `class` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `table` varchar(70) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `hide` tinyint(1) NOT NULL DEFAULT \'0\',
              `percentage_surcharge` double DEFAULT NULL,
              `absolute_surcharge` double DEFAULT NULL,
              `surcharge_string` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `position` int(11) NOT NULL DEFAULT \'1\',
              `active` tinyint(1) NOT NULL DEFAULT \'0\',
              `allow_esd` tinyint(1) NOT NULL DEFAULT \'0\',
              `used_iframe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `hide_prospect` tinyint(1) NOT NULL DEFAULT \'1\',
              `action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `source` int(11) DEFAULT NULL,
              `mobile_inactive` tinyint(1) NOT NULL DEFAULT \'0\',
              `risk_rules` longtext COLLATE utf8mb4_unicode_ci,
              `plugin_id` VARCHAR(250) DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              UNIQUE KEY `name` (`technical_name`, `version_id`, `tenant_id`),
              CONSTRAINT `fk_payment_method.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
