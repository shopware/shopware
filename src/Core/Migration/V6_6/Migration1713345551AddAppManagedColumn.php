<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1713345551AddAppManagedColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1713345551;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app', 'self_managed', 'TINYINT(1)', false, '0');
    }
}
