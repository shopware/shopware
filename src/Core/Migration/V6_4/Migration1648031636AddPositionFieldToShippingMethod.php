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
class Migration1648031636AddPositionFieldToShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1648031636;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'shipping_method', 'position')) {
            $connection->executeStatement('ALTER TABLE `shipping_method` ADD `position` INT(11) NOT NULL DEFAULT 1 AFTER `active`;');
        }
    }
}
