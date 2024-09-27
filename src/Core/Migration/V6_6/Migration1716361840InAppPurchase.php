<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1716361840InAppPurchase extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1716361840;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `in_app_purchase` (
              `identifier`              VARCHAR(255)        NOT NULL,
              `expires_at`              DATETIME(3)         NOT NULL,
              `active`                  TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `app_id`                  BINARY(16)          NULL,
              `plugin_id`               BINARY(16)          NULL,
              `created_at`              DATETIME(3)         NOT NULL,
              `updated_at`              DATETIME(3)         NULL,
               PRIMARY KEY (`identifier`),
               CONSTRAINT `uniq.in_app_purchase.identifier`
                 UNIQUE (`identifier`),
               CONSTRAINT `fk.in_app_purchase.app_id` FOREIGN KEY (`app_id`)
                 REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
               CONSTRAINT `fk.in_app_purchase.plugin_id` FOREIGN KEY (`plugin_id`)
                 REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
               INDEX (`expires_at`),
               INDEX (`active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
