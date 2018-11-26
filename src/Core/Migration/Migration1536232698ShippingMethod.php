<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232698ShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232698;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `shipping_method` (
              `id` binary(16) NOT NULL,
              `type` int(11) unsigned NOT NULL,
              `active` tinyint(1) unsigned NOT NULL DEFAULT \'1\',
              `position` int(11) NOT NULL DEFAULT \'1\',
              `calculation` int(1) unsigned NOT NULL DEFAULT \'0\',
              `surcharge_calculation` int(1) unsigned DEFAULT NULL,
              `tax_calculation` int(11) unsigned NOT NULL DEFAULT \'0\',
              `min_delivery_time` int(11) DEFAULT \'1\',
              `max_delivery_time` int(11) DEFAULT \'2\',
              `shipping_free` decimal(10,2) unsigned DEFAULT NULL,
              `bind_shippingfree` tinyint(1) NOT NULL,
              `bind_time_from` int(11) unsigned DEFAULT NULL,
              `bind_time_to` int(11) unsigned DEFAULT NULL,
              `bind_instock` tinyint(1) DEFAULT NULL,
              `bind_laststock` tinyint(1) NULL,
              `bind_weekday_from` int(1) unsigned DEFAULT NULL,
              `bind_weekday_to` int(1) unsigned DEFAULT NULL,
              `bind_weight_from` decimal(10,3) DEFAULT NULL,
              `bind_weight_to` decimal(10,3) DEFAULT NULL,
              `bind_price_from` decimal(10,2) DEFAULT NULL,
              `bind_price_to` decimal(10,2) DEFAULT NULL,
              `bind_sql` mediumtext COLLATE utf8mb4_unicode_ci,
              `status_link` mediumtext COLLATE utf8mb4_unicode_ci,
              `calculation_sql` mediumtext COLLATE utf8mb4_unicode_ci,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
