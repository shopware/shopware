<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1762246952IncreaseKgDisplayPrecision extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1762246952;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE measurement_display_unit
             SET `precision` = 6
             WHERE `short_name` = :shortName
               AND `updated_at` IS NULL
               AND `precision` = 2',
            ['shortName' => 'kg']
        );

        $connection->executeStatement(
            'UPDATE measurement_display_unit
             SET `precision` = 3
             WHERE `short_name` = :shortName
               AND `updated_at` IS NULL
               AND `precision` = 2',
            ['shortName' => 'mm']
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
