<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1605609477RemoveDeleteCascadeConstraintInDocumentBaseConfigTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1605609477;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `document_base_config` DROP FOREIGN KEY `fk.document_base_config.logo_id`;');
        $connection->executeUpdate('ALTER TABLE `document_base_config` ADD CONSTRAINT `fk.document_base_config.logo_id` FOREIGN KEY (`logo_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
