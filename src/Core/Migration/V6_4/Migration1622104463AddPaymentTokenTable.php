<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1622104463AddPaymentTokenTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1622104463;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `payment_token` (
              `token` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
              `expires` datetime(3) NOT NULL,
              PRIMARY KEY (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
