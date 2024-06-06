<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('services-settings')]
class Migration1717572627RemoveImportExportProfileName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1717572627;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        if ($this->columnExists($connection, 'import_export_profile', 'name')) {
            $connection->executeStatement('ALTER TABLE `import_export_profile` DROP COLUMN `name`');
        }
    }
}
