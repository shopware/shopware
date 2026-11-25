<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1795597750AddMediaIdScanIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795597750;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'media', 'idx_media_id_only')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `media` ADD INDEX `idx_media_id_only` (`id`), ALGORITHM=INPLACE, LOCK=NONE');
    }
}
