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
class Migration1649315608AllowDisable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649315608;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'app', 'allow_disable')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `app` ADD `allow_disable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `active`;');
    }
}
