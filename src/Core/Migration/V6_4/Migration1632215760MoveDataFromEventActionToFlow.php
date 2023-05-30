<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1632215760MoveDataFromEventActionToFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1632215760;
    }

    public function update(Connection $connection): void
    {
        $migrate = new Migration1625583619MoveDataFromEventActionToFlow();
        $migrate->internal = true;
        $migrate->update($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
