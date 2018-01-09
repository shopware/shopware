<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\ListingPrice;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Dbal\EntityDefinitionResolver;
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

    public function load(array $productIds): ProductListingPriceBasicCollection
    {
        $query = $this->connection->createQueryBuilder();
        $query->addSelect([
            'product.id',
            'tax.tax_rate',
            'product_price.customer_group_id as customer_group_id',
            'MIN(product_price.price) as price',
            'COUNT(DISTINCT(product_price.price)) as display_from_price',
        ]);

        $productIds = EntityDefinitionResolver::uuidStringsToBytes($productIds);

        $query->from('product');
        $query->innerJoin('product', 'product_price', 'product_price', 'product_price.product_id = product.id');
        $query->innerJoin('product', 'tax', 'tax', 'tax.id = product.tax_id');
        $query->andWhere('product.id IN (:ids)');
        $query->setParameter('ids', $productIds, Connection::PARAM_STR_ARRAY);
        $query->addGroupBy('product.id');
        $query->addGroupBy('product_price.customer_group_id');
        $query->addGroupBy('tax.tax_rate');

        $rows = $query->execute()->fetchAll();
        $collection = new ProductListingPriceBasicCollection();
        foreach ($rows as $row) {
            $struct = new ProductListingPriceBasicStruct();
            $struct->setId(Uuid::uuid4()->toString());
            $struct->setProductId(Uuid::fromBytes($row['id'])->toString());
            $gross = ((float) $row['price']) * ((100 + $row['tax_rate']) / 100);

            $struct->setSortingPrice($gross);
            $struct->setPrice((float) $row['price']);
            $struct->setCustomerGroupId(Uuid::fromBytes($row['customer_group_id'])->toString());
            $struct->setDisplayFromPrice(((int) $row['display_from_price']) > 1);
            $collection->add($struct);
        }

        return $collection;
    }
}
