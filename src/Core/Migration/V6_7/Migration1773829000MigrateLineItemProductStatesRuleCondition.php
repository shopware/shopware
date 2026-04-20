<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773829000MigrateLineItemProductStatesRuleCondition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773829000;
    }

    public function update(Connection $connection): void
    {
        // Intentionally empty.
        //
        // This migration originally converted cartLineItemProductStates rule conditions
        // to cartLineItemProductType during the 6.7 upgrade. However, 6.6 code cannot
        // evaluate cartLineItemProductType (LineItemProductTypeRule was introduced in 6.7),
        // so running this conversion in update() breaks blue-green deployments where 6.6
        // pods are still running while the 6.7 DB migration has already been applied.
        //
        // The conversion is now performed in V6_8\Migration1763125891, which runs during
        // the 6.7 → 6.8 upgrade after the 6.6/6.7 blue-green window has definitively
        // closed and before LineItemProductStatesRule is removed from the codebase.
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
