<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1615359964AddTaxFreeFromAmountForCurrencyAndCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1615359964;
    }

    public function update(Connection $connection): void
    {
        $featureCountryColumn = $connection->fetchOne(
            'SHOW COLUMNS FROM `country` WHERE `Field` LIKE :column;',
            ['column' => 'tax_free_from']
        );

        if ($featureCountryColumn === false) {
            $connection->executeStatement('
            ALTER TABLE `country` ADD COLUMN `tax_free_from` DOUBLE DEFAULT 0 AFTER `shipping_available`;
            ');
        }

        $featureCurrencyColumn = $connection->fetchOne(
            'SHOW COLUMNS FROM `currency` WHERE `Field` LIKE :column;',
            ['column' => 'tax_free_from']
        );

        if ($featureCurrencyColumn === false) {
            $connection->executeStatement('
            ALTER TABLE `currency` ADD COLUMN `tax_free_from` DOUBLE DEFAULT 0 AFTER `total_rounding`;
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
