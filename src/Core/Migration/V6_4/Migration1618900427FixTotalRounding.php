<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1618900427FixTotalRounding extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1618900427;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `order`, `currency`
            SET `order`.item_rounding = currency.item_rounding,
                `order`.`total_rounding` = currency.item_rounding
            WHERE `order`.currency_id = currency.id
        ');

        $rounding = json_encode([
            'decimals' => 2,
            'interval' => 0.01,
            'roundForNet' => true,
        ]);

        $connection->executeStatement('UPDATE `order` SET item_rounding = :rounding WHERE item_rounding IS NULL', ['rounding' => $rounding]);
        $connection->executeStatement('UPDATE `order` SET total_rounding = :rounding WHERE total_rounding IS NULL', ['rounding' => $rounding]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
