<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * Shared widening of {@see ProductDefinition}'s `display_group` from VARCHAR(50) to VARCHAR(64)
 * so SHA-256 hex hashes (64 characters) fit. Used by multiple migrations that first write SHA2().
 *
 * InnoDB can widen VARCHAR within the 255-byte limit with ALGORITHM=INPLACE on supported versions,
 * but the operation still acquires a metadata lock on `product` briefly. Plan upgrades accordingly
 * on very large catalogs.
 *
 * @internal
 */
#[Package('framework')]
final class ProductDisplayGroupColumnMigrationHelper
{
    private function __construct()
    {
    }

    public static function widenVarchar50To64ForSha256IfNeeded(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, ProductDefinition::ENTITY_NAME, 'display_group')) {
            return;
        }

        $column = TableHelper::getColumnOfTable($connection, ProductDefinition::ENTITY_NAME, 'display_group');

        // DBAL maps MySQL VARCHAR to StringType; Type::lookupName() yields 'string'.
        if ($column->type !== 'string' || $column->length !== 50) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `product` MODIFY `display_group` VARCHAR(64) NULL');
    }
}
