<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1652864153ReindexMediaFolders extends MigrationStep
{
    /**
     * @codeCoverageIgnore
     */
    public function getCreationTimestamp(): int
    {
        return 1652864153;
    }

    public function update(Connection $connection): void
    {
        if ($this->isInstallation()) {
            return;
        }

        $this->registerIndexer($connection, 'media_folder.indexer', [MediaFolderIndexer::CHILD_COUNT_UPDATER]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
