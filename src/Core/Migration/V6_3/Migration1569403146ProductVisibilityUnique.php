<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1569403146ProductVisibilityUnique extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1569403146;
    }

    public function update(Connection $connection): void
    {
        $removeDuplicatesSql = <<<'SQL'
DELETE t1 FROM product_visibility t1
INNER JOIN product_visibility t2
WHERE
    (t1.created_at < t2.created_at OR (t1.created_at = t2.created_at AND t1.id < t2.id)) AND
    t1.product_id = t2.product_id AND
    t1.product_version_id = t2.product_version_id AND
    t1.sales_channel_id = t2.sales_channel_id;
SQL;

        $connection->executeStatement($removeDuplicatesSql);

        $connection->executeStatement('
            ALTER TABLE `product_visibility`
                ADD UNIQUE KEY `uniq.product_id__sales_channel_id` (`product_id`, `product_version_id`, `sales_channel_id`)
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
