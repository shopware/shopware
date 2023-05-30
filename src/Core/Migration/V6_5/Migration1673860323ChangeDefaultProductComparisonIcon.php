<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1673860323ChangeDefaultProductComparisonIcon extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673860323;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `sales_channel_type` SET `icon_name` = "regular-rocket" WHERE `icon_name` = "default-object-rocket"');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
