<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1552360944MediaFolderConfigurationNoAssoc extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552360944;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `media_folder_configuration`
                ADD COLUMN `no_association` BOOL NULL AFTER `private`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
