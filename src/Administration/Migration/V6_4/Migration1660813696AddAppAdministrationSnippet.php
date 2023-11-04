<?php declare(strict_types=1);

namespace Shopware\Administration\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1660813696AddAppAdministrationSnippet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1660813696;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_administration_snippet` (
                `id` BINARY(16) NOT NULL,
                `app_id` BINARY(16) NOT NULL,
                `locale_id` BINARY(16) NOT NULL,
                `value` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.app_id` FOREIGN KEY (`app_id`)
                    REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.locale_id` FOREIGN KEY (`locale_id`)
                    REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
