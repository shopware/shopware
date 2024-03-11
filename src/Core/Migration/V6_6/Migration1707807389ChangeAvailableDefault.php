<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1707807389ChangeAvailableDefault extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1707807389;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product` CHANGE `available` `available` tinyint(1) NOT NULL DEFAULT \'0\';');
    }
}
