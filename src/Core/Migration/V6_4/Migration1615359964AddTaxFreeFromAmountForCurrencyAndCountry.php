<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1615359964AddTaxFreeFromAmountForCurrencyAndCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1615359964;
    }

    public function update(Connection $connection): void
    {
        $featureCountryColumn = $connection->fetchColumn(
            'SHOW COLUMNS FROM `country` WHERE `Field` LIKE :column;',
            ['column' => 'tax_free_from']
        );

        if ($featureCountryColumn === false) {
            $connection->executeUpdate('
            ALTER TABLE `country` ADD COLUMN `tax_free_from` DOUBLE DEFAULT 0 AFTER `shipping_available`;
            ');
        }

        $featureCurrencyColumn = $connection->fetchColumn(
            'SHOW COLUMNS FROM `currency` WHERE `Field` LIKE :column;',
            ['column' => 'tax_free_from']
        );

        if ($featureCurrencyColumn === false) {
            $connection->executeUpdate('
            ALTER TABLE `currency` ADD COLUMN `tax_free_from` DOUBLE DEFAULT 0 AFTER `total_rounding`;
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
