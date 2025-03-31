<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1727766331 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1727766331;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
                CREATE TABLE IF NOT EXISTS `snippet_set_locale` (
                `id` BINARY(16) NOT NULL,
                `locale_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.snippet_set_locale.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}

