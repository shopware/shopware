<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1631703921MigrateLineItemsInCartRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1631703921;
    }

    public function update(Connection $connection): void
    {
        // moved to V6_5/Migration1669291632MigrateLineItemsInCartRule.php
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
