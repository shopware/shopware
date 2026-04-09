<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Migration\ProductDisplayGroupColumnMigrationHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775200001IncreaseProductDisplayGroupLength extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775200001;
    }

    public function update(Connection $connection): void
    {
        ProductDisplayGroupColumnMigrationHelper::widenVarchar50To64ForSha256IfNeeded($connection);
    }
}
