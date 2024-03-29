<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1702479623RemoveEsMultilingualFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1702479623;
    }

    public function update(Connection $connection): void
    {
        $storage = new MySQLKeyValueStorage($connection);

        $storage->remove('enable-multilingual-index');
    }
}
