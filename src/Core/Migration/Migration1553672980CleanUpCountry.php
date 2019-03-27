<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553672980CleanUpCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553672980;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `country`
            DROP COLUMN `shipping_free`,
            DROP COLUMN `taxfree_for_vat_id`,
            DROP COLUMN `taxfree_vatid_checked`
            ;'
        );
    }
}
