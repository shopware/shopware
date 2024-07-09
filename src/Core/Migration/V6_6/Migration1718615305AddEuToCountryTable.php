<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1718615305AddEuToCountryTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1718615305;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
            ALTER TABLE `country`
            ADD COLUMN `is_eu` BOOLEAN NOT NULL DEFAULT 0;
            SQL,
        );

        $connection->executeStatement(
            <<<SQL
            UPDATE `country`
            SET `is_eu` = 1
            WHERE `iso` IN (:euCountryIsoCodes);
            SQL,
            [
                'euCountryIsoCodes' => [
                    'AT', // Austria
                    'BE', // Belgium
                    'BG', // Bulgaria
                    'CY', // Cyprus
                    'CZ', // Czech Republic
                    'DE', // Germany
                    'DK', // Denmark
                    'EE', // Estonia
                    'ES', // Spain
                    'FI', // Finland
                    'FR', // France
                    'GR', // Greece
                    'HR', // Croatia
                    'HU', // Hungary
                    'IE', // Ireland
                    'IT', // Italy
                    'LT', // Lithuania
                    'LU', // Luxembourg
                    'LV', // Latvia
                    'MT', // Malta
                    'NL', // Netherlands
                    'PL', // Poland
                    'PT', // Portugal
                    'RO', // Romania
                    'SE', // Sweden
                    'SI', // Slovenia
                    'SK', // Slovakia
                ],
            ],
            [
                'euCountryIsoCodes' => ArrayParameterType::STRING,
            ],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
