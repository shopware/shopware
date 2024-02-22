<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1663402842AddPathToMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1663402842;
    }

    public function update(Connection $connection): void
    {
        $this->swAddColumn(
            $connection,
            'media',
            'path',
            'VARCHAR(2048)'
        );

        $this->swAddColumn(
            $connection,
            'media_thumbnail',
            'path',
            'VARCHAR(2048)'
        );

        $this->registerIndexer($connection, 'media.path.post_update');
    }
}
