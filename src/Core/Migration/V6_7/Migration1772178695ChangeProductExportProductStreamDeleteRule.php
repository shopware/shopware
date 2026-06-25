<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1772178695ChangeProductExportProductStreamDeleteRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772178695;
    }

    public function update(Connection $connection): void
    {
        /** @phpstan-ignore shopware.dropStatement (FK is directly added again so dropping the FK is no issue for blue green) */
        $this->dropForeignKeyIfExists($connection, 'product_export', 'fk.product_export.product_stream_id');

        $connection->executeStatement(
            <<<'SQL'
            ALTER TABLE `product_export`
                ADD CONSTRAINT `fk.product_export.product_stream_id`
                    FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
SQL
        );
    }
}
