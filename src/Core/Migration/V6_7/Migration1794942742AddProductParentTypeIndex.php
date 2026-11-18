<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1794942742AddProductParentTypeIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794942742;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'product', 'idx.product.parent_type')) {
            return;
        }

        $this->executeDdlStatement($connection, <<<'SQL'
            ALTER TABLE `product`
                ADD INDEX `idx.product.parent_type` (`parent_id`, `parent_version_id`, `type`),
                ALGORITHM=INPLACE,
                LOCK=NONE
            SQL);
    }
}
