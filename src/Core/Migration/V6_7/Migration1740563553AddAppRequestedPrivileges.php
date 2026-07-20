<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1740563553AddAppRequestedPrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1740563553;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'app',
            'requested_privileges',
            'json',
        );
    }
}
