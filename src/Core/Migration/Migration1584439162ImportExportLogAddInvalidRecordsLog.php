<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1584439162ImportExportLogAddInvalidRecordsLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1584439162;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'ALTER TABLE `import_export_log`
            ADD COLUMN `invalid_records_log_id` BINARY(16),
            ADD CONSTRAINT `fk.import_export_log.invalid_records_log_id` 
                FOREIGN KEY (`invalid_records_log_id`)
                REFERENCES `import_export_log` (`id`)
                ON DELETE SET NULL'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
