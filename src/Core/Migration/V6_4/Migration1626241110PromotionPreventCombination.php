<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1626241110PromotionPreventCombination extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1626241110;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `promotion` ADD COLUMN `prevent_combination` TINYINT(1) NOT NULL DEFAULT 0 AFTER `customer_restriction`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
