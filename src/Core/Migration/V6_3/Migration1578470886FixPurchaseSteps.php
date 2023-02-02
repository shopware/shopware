<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1578470886FixPurchaseSteps extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578470886;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('UPDATE product SET purchase_steps = 1 WHERE purchase_steps < 1');
        $connection->executeUpdate('UPDATE product SET min_purchase = 1 WHERE min_purchase < 1');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
