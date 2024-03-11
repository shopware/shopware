<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('services-settings')]
class Migration1692277552FixCustomerReviewCount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1692277552;
    }

    public function update(Connection $connection): void
    {
        $offset = 0;
        do {
            $result = $connection->executeStatement('
                UPDATE `customer`
                INNER JOIN (
                    SELECT `product_review`.customer_id,
                    COUNT(`product_review`.id) as review_count
                    FROM `product_review`
                    WHERE `product_review`.status = 1
                    GROUP BY `product_review`.customer_id
                    LIMIT 1000
                    OFFSET :offset
                ) AS meta_data ON `meta_data`.customer_id = `customer`.id
                SET `customer`.review_count = `meta_data`.review_count
            ', ['offset' => $offset], ['offset' => \PDO::PARAM_INT]);
            $offset += 1000;
        } while ($result > 0);
    }
}
