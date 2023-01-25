<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1554200141ImportExportFile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554200141;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `import_export_file` (
              `id` binary(16) NOT NULL,
              `original_name` VARCHAR(255) NOT NULL,
              `path` VARCHAR(255) NOT NULL,
              `expire_date` datetime(3) NOT NULL,
              `size` INT(11),
              `updated_at` datetime(3) NULL,
              `created_at` datetime(3) NOT NULL,
              `access_token` VARCHAR(255) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
