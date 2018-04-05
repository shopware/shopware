<?php

namespace Shopware\DbalIndexing\Product;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Struct\Uuid;

class VariationJsonUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(array $productIds)
    {
        $sql = <<<SQL
UPDATE product, product_variation SET product.variation_ids = (
    SELECT CONCAT(
        '[',
        GROUP_CONCAT(
            CONCAT('\"', LOWER(HEX(product_variation.configuration_group_option_id)), '\"') 
            SEPARATOR ','
        ),
        ']'
    )
    FROM product_variation
    WHERE product_variation.product_id = product.id
)
WHERE product_variation.product_id = product.id
SQL;

        if (empty($productIds)) {
            $this->connection->executeUpdate($sql);
            return;
        }

        $sql .= ' AND product.id IN (:ids)';

        $bytes = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $productIds);

        $this->connection->executeUpdate($sql, ['ids' => $bytes], ['ids' => Connection::PARAM_STR_ARRAY]);
    }
}