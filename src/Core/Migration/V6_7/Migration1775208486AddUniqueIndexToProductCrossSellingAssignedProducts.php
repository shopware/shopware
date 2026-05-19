<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775208486AddUniqueIndexToProductCrossSellingAssignedProducts extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775208486;
    }

    public function update(Connection $connection): void
    {
        if ($this->indexExists($connection, 'product_cross_selling_assigned_products', 'uniq.cross_selling_id__product_id__product_version_id')) {
            return;
        }

        $removeDuplicatesSql = <<<'SQL'
DELETE t1 FROM product_cross_selling_assigned_products t1
INNER JOIN product_cross_selling_assigned_products t2
WHERE
    (t1.created_at < t2.created_at OR (t1.created_at = t2.created_at AND t1.id < t2.id)) AND
    t1.cross_selling_id = t2.cross_selling_id AND
    t1.product_id = t2.product_id AND
    t1.product_version_id = t2.product_version_id;
SQL;

        $connection->executeStatement($removeDuplicatesSql);

        $connection->executeStatement('
            ALTER TABLE `product_cross_selling_assigned_products`
                ADD UNIQUE KEY `uniq.cross_selling_id__product_id__product_version_id` (`cross_selling_id`, `product_id`, `product_version_id`)
        ');
    }
}
