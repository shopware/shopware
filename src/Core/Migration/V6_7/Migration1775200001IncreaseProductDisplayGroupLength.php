<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775200001IncreaseProductDisplayGroupLength extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775200001;
    }

    public function update(Connection $connection): void
    {
        $this->widenDisplayGroupColumnForSha256IfNeeded($connection);
    }

    private function widenDisplayGroupColumnForSha256IfNeeded(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, ProductDefinition::ENTITY_NAME, 'display_group')) {
            return;
        }

        $column = TableHelper::getColumnOfTable($connection, ProductDefinition::ENTITY_NAME, 'display_group');

        if ($column->type === 'string' && $column->length !== null && $column->length >= 64) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `product` MODIFY `display_group` VARCHAR(64) NULL');
    }
}
