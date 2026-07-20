<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1718615305AddEuToCountryTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1718615305;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'country', 'is_eu')) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `country`
             ADD COLUMN `is_eu` BOOLEAN NOT NULL DEFAULT 0;',
        );

        $connection->executeStatement(
            'UPDATE `country`
             SET `is_eu` = 1
             WHERE `iso` IN (:euCountryIsoCodes);',
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
            ['euCountryIsoCodes' => ArrayParameterType::STRING],
        );
    }
}
