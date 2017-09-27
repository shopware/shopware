<?php

namespace Shopware\Denormalization\Product;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Repository\CustomerGroupRepository;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicStruct;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\Storefront\Context\StorefrontContextService;

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

    /**
     * @var CustomerGroupRepository
     */
    private $customerGroupRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Connection $connection,
        ListingPriceLoader $listingPriceLoader,
        CustomerGroupRepository $customerGroupRepository,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->listingPriceLoader = $listingPriceLoader;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->logger = $logger;
    }

    public function index(array $uuids, TranslationContext $context): void
    {
        $this->indexListingPrices($uuids, $context);

        $this->refreshCategoryAssignment($uuids, $context);
    }

    public function indexListingPrices(array $uuids, TranslationContext $context): void
    {
        $this->connection->transactional(function () use ($uuids, $context) {
            $customerGroups = $this->customerGroupRepository->searchUuids(new Criteria(), $context);

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

            foreach ($uuids as $productUuid) {
                $prices = $this->prepareProductPrices($productUuid, $listingPrices, $customerGroups);

                /** @var ProductListingPriceBasicStruct $price */
                foreach ($prices as $price) {
                    $insert->execute([
                        'uuid' => $price->getUuid(),
                        'product_uuid' => $price->getProductUuid(),
                        'customer_group_uuid' => $price->getCustomerGroupUuid(),
                        'price' => $price->getPrice(),
                    ]);
                }
            }
        });
    }

    private function prepareProductPrices(
        string $productUuid,
        ProductListingPriceBasicCollection $listingPrices,
        UuidSearchResult $customerGroups
    ): ProductListingPriceBasicCollection {
        $prices = $listingPrices->filterByProductUuid($productUuid);

        $fallback = $prices->filterByCustomerGroupUuid(StorefrontContextService::FALLBACK_CUSTOMER_GROUP);

        if ($fallback->count() <= 0) {
            $this->logger->log(Logger::WARNING, sprintf('Product %s has no default customer group price', $productUuid));
            return $prices;
        }

        $fallback = $fallback->first();

        /** @var ProductListingPriceBasicStruct $fallback */
        foreach ($customerGroups->getUuids() as $uuid) {
            if ($uuid === StorefrontContextService::FALLBACK_CUSTOMER_GROUP) {
                continue;
            }

            //check if customer prices exists
            $customerPrice = $prices->filterByCustomerGroupUuid($uuid);
            if ($customerPrice->count() > 0) {
                continue;
            }

            $customerPrice = clone $fallback;
            $customerPrice->setCustomerGroupUuid($uuid);
            $customerPrice->setUuid(Uuid::uuid4()->toString());
            $prices->add($customerPrice);
        }

        return $prices;
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
