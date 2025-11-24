<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Fixtures\VX_X;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1763996000Dummy extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763996000;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
