<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\ListingPrice;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Product\Collection\ProductListingPriceBasicCollection;
use Shopware\Api\Product\Struct\ProductListingPriceBasicStruct;
use Shopware\Cart\Price\PriceCalculator;

class ListingPriceLoader
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var PriceCalculator
     */
    private $calculator;

    public function __construct(Connection $connection, PriceCalculator $calculator)
    {
        $this->connection = $connection;
        $this->calculator = $calculator;
    }

    public function load(array $productUuids): ProductListingPriceBasicCollection
    {
        $query = $this->connection->createQueryBuilder();
        $query->addSelect([
            'product.uuid',
            'tax.tax_rate',
            'product_price.customer_group_uuid as customer_group_uuid',
            'MIN(product_price.price) as price',
            'COUNT(DISTINCT(product_price.price)) as display_from_price',
        ]);

        $query->from('product');
        $query->innerJoin('product', 'product_price', 'product_price', 'product_price.product_uuid = product.uuid');
        $query->innerJoin('product', 'tax', 'tax', 'tax.uuid = product.tax_uuid');
        $query->andWhere('product.uuid IN (:uuids)');
        $query->setParameter(':uuids', $productUuids, Connection::PARAM_STR_ARRAY);
        $query->addGroupBy('product.uuid');
        $query->addGroupBy('product_price.customer_group_uuid');
        $query->addGroupBy('tax.tax_rate');

        $rows = $query->execute()->fetchAll();
        $collection = new ProductListingPriceBasicCollection();
        foreach ($rows as $row) {
            $struct = new ProductListingPriceBasicStruct();
            $struct->setUuid(Uuid::uuid4()->toString());
            $struct->setProductUuid($row['uuid']);
            $gross = ((float) $row['price']) * ((100 + $row['tax_rate']) / 100);

            $struct->setSortingPrice($gross);
            $struct->setPrice((float) $row['price']);
            $struct->setCustomerGroupUuid($row['customer_group_uuid']);
            $struct->setDisplayFromPrice(((int) $row['display_from_price']) > 1);
            $collection->add($struct);
        }

        return $collection;
    }
}
