<?php

namespace Shopware\DbalIndexing\Product;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Struct\Uuid;

class DatasheetJsonUpdater
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
UPDATE product, product_datasheet SET product.datasheet_ids = (
    SELECT CONCAT('[', GROUP_CONCAT(JSON_QUOTE(LOWER(HEX(product_datasheet.configuration_group_option_id)))), ']')
    FROM product_datasheet
    WHERE product_datasheet.product_id = product.datasheet_join_id
)
WHERE product_datasheet.product_id = product.datasheet_join_id
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