<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1615359964AddTaxFreeFromAmountForCurrencyAndCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1615359964;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'country', 'tax_free_from')) {
            $connection->executeStatement('
            ALTER TABLE `country` ADD COLUMN `tax_free_from` DOUBLE DEFAULT 0 AFTER `shipping_available`;
            ');
        }

        if (!TableHelper::columnExists($connection, 'currency', 'tax_free_from')) {
            $connection->executeStatement('
            ALTER TABLE `currency` ADD COLUMN `tax_free_from` DOUBLE DEFAULT 0 AFTER `total_rounding`;
            ');
        }
    }
}
