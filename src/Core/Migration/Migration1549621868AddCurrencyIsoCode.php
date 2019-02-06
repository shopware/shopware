<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549621868AddCurrencyIsoCode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549621868;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `currency` ADD COLUMN `iso_code` char(3) NULL AFTER `id`;');

        $connection->exec("UPDATE `currency` SET `iso_code` = 'EUR' WHERE `symbol` = '€';");
        $connection->exec("UPDATE `currency` SET `iso_code` = 'GBP' WHERE `symbol` = '£';");
        $connection->exec("UPDATE `currency` SET `iso_code` = 'USD' WHERE `symbol` = '$';");

        $connection->exec('ALTER TABLE `currency` MODIFY COLUMN `iso_code` char(3) NOT NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
