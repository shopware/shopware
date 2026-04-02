<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775180400AddNextGenerationAtToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775180400;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'product_export', 'next_generation_at')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `product_export` ADD COLUMN `next_generation_at` DATETIME(3) NULL AFTER `generated_at`');
    }

    public function updateDestructive(Connection $connection): void
    {
        // Implement updateDestructive() method.
    }
}
