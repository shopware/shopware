<?php

namespace Shopware\Denormalization\Product;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicStruct;

class ProductIndexer
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ListingPriceLoader
     */
    private $listingPriceLoader;

    public function __construct(Connection $connection, ListingPriceLoader $listingPriceLoader)
    {
        $this->connection = $connection;
        $this->listingPriceLoader = $listingPriceLoader;
    }

    public function index(array $uuids, TranslationContext $context): void
    {
        $this->indexListingPrices($uuids, $context);

        $this->refreshCategoryAssignment($uuids, $context);
    }

    public function indexListingPrices(array $uuids, TranslationContext $context): void
    {
        $this->connection->transactional(function () use ($uuids) {
            $listingPrices = $this->listingPriceLoader->load($uuids);

            $insert = $this->connection->prepare('
                INSERT INTO product_listing_price_ro (uuid, product_uuid, customer_group_uuid, price)
                VALUES (:uuid, :product_uuid, :customer_group_uuid, :price)
            ');

            $this->connection->executeUpdate(
                'DELETE FROM product_listing_price_ro WHERE product_uuid IN (:uuids)',
                ['uuids' => $uuids],
                ['uuids' => Connection::PARAM_STR_ARRAY]
            );

            /** @var ProductListingPriceBasicStruct $price */
            foreach ($listingPrices as $price) {
                $insert->execute([
                    'uuid' => $price->getUuid(),
                    'product_uuid' => $price->getProductUuid(),
                    'customer_group_uuid' => $price->getCustomerGroupUuid(),
                    'price' => $price->getPrice(),
                ]);
            }
        });
    }

    public function refreshCategoryAssignment(array $uuids, $context): void
    {
        $this->connection->transactional(function () use ($uuids) {
            $categories = $this->fetchCategories($uuids);

            $this->connection->executeUpdate(
                'DELETE FROM product_category_ro WHERE product_uuid IN (:uuids)',
                ['uuids' => $uuids],
                ['uuids' => Connection::PARAM_STR_ARRAY]
            );

            $insert = $this->connection->prepare(
                'INSERT IGNORE INTO product_category_ro (product_uuid, category_uuid) VALUES (:product_uuid, :category_uuid)'
            );

            foreach ($categories as $productUuid => $mapping) {
                $categoryUuids = array_merge(
                    explode('|', $mapping['paths']),
                    explode('|', $mapping['uuids'])
                );

                $categoryUuids = array_keys(array_flip(array_filter($categoryUuids)));

                foreach ($categoryUuids as $uuid) {
                    $insert->execute([
                        'product_uuid' => $productUuid,
                        'category_uuid' => $uuid,
                    ]);
                }
            }
        });
    }

    private function fetchCategories(array $uuids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'product.uuid as product_uuid',
            "GROUP_CONCAT(category.path SEPARATOR '|') as paths",
            "GROUP_CONCAT(category.uuid SEPARATOR '|') as uuids",
        ]);
        $query->from('product');
        $query->leftJoin('product', 'product_category', 'mapping', 'mapping.product_uuid = product.uuid');
        $query->leftJoin('mapping', 'category', 'category', 'category.uuid = mapping.category_uuid');
        $query->addGroupBy('product.uuid');
        $query->andWhere('product.uuid IN (:uuids)');
        $query->setParameter(':uuids', $uuids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    }
}
