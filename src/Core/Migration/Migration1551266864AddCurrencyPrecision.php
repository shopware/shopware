<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551266864AddCurrencyPrecision extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551266864;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `currency` ADD `decimal_precision` INT(11) NULL AFTER `position`;');
        $connection->executeUpdate('UPDATE `currency` SET `decimal_precision` = 2;');
        $connection->executeUpdate('ALTER TABLE `currency` CHANGE `decimal_precision` `decimal_precision` int(11) NOT NULL AFTER `position`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
