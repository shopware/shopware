<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @see \Shopware\Core\Migration\V6_5\Migration1708685282MigrateToReferencedColumns
 * This migration is left empty intentionally, to prevent possible side effects.
 * The content has been moved to the 6.5 migration namespace, so the change is executed with 6.7 and not just with 6.8.
 */
#[Package('core')]
class Migration1673964565MigrateToReferencedColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673964565;
    }

    public function update(Connection $connection): void
    {
    }
}
