<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1697532722FixADInMediaPath extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697532722;
    }

    public function update(Connection $connection): void
    {
        // replace /ad/ with /g0/ in media.path and media_thumbnail.path
        $connection->executeQuery('UPDATE media SET path = REPLACE(path, \'/ad/\', \'/g0/\') WHERE path LIKE \'%/ad/%\'');
        $connection->executeQuery('UPDATE media_thumbnail SET path = REPLACE(path, \'/ad/\', \'/g0/\') WHERE path LIKE \'%/ad/%\'');
    }
}
