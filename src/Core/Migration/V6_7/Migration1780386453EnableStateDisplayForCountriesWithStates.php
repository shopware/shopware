<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1780386453EnableStateDisplayForCountriesWithStates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780386453;
    }

    public function update(Connection $connection): void
    {
        // Preserve the previous storefront behavior when the display flag starts being used:
        // countries with at least one state should keep showing the state field.
        // EXISTS checks only whether a state is present, independent of the number of state rows.
        $connection->executeStatement('
            UPDATE `country` c
            SET c.`display_state_in_registration` = 1
            WHERE c.`display_state_in_registration` = 0
                AND EXISTS (
                    SELECT 1
                    FROM `country_state` cs
                    WHERE cs.`country_id` = c.`id`
                )
        ');
    }
}
