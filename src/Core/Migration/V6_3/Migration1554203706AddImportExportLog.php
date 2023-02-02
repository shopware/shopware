<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1554203706AddImportExportLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554203706;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `import_export_log` (
              `id` binary(16) NOT NULL,
              `activity` varchar(255) NOT NULL,
              `state` varchar(255) NOT NULL,
              `records` INT(11) NOT NULL,
              `username` varchar(255) NULL,
              `profile_name` varchar(255) NULL,
              `user_id` binary(16),
              `profile_id` binary(16),
              `file_id` binary(16),
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.import_export_log.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk.import_export_log.profile_id` FOREIGN KEY (`profile_id`) REFERENCES `import_export_profile` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk.import_export_log.file_id` FOREIGN KEY (`file_id`) REFERENCES `import_export_file` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
