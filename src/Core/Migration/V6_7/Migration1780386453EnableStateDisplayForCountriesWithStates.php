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
        $connection->executeStatement('
            UPDATE `country` c
            INNER JOIN `country_state` cs
                ON cs.`country_id` = c.`id`
            SET c.`display_state_in_registration` = 1
            WHERE c.`display_state_in_registration` = 0
        ');
    }
}
