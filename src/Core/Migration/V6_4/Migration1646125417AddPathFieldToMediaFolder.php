<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1646125417AddPathFieldToMediaFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1646125417;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'media_folder', 'path')) {
            $connection->executeStatement('ALTER TABLE `media_folder` ADD `path` longtext NULL AFTER `child_count`;');
        }

        $this->registerIndexer($connection, 'media_folder.indexer');
    }
}
