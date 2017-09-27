<?php

namespace Shopware\Product\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Loader\CustomerGroupBasicLoader;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Product\Factory\ProductBasicFactory;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\ProductDetail\Loader\ProductDetailBasicLoader;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearcher;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class ProductBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductBasicFactory
     */
    private $factory;

    /**
     * @var ProductDetailBasicLoader
     */
    private $productDetailBasicLoader;

    /**
     * @var CustomerGroupBasicLoader
     */
    private $customerGroupBasicLoader;

    /**
     * @var ProductListingPriceSearcher
     */
    private $productListingPriceSearcher;

    public function __construct(
        ProductBasicFactory $factory,
        ProductDetailBasicLoader $productDetailBasicLoader,
        CustomerGroupBasicLoader $customerGroupBasicLoader,
        ProductListingPriceSearcher $productListingPriceSearcher
    ) {
        $this->factory = $factory;
        $this->productDetailBasicLoader = $productDetailBasicLoader;
        $this->customerGroupBasicLoader = $customerGroupBasicLoader;
        $this->productListingPriceSearcher = $productListingPriceSearcher;
    }

    public function load(array $uuids, TranslationContext $context): ProductBasicCollection
    {
        if (empty($uuids)) {
            return new ProductBasicCollection();
        }

        $productsCollection = $this->read($uuids, $context);

        $mainDetails = $this->productDetailBasicLoader->load($productsCollection->getMainDetailUuids(), $context);

        $blockedCustomerGroups = $this->customerGroupBasicLoader->load($productsCollection->getBlockedCustomerGroupsUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_listing_price_ro.product_uuid', $uuids));
        /** @var ProductListingPriceSearchResult $listingPrices */
        $listingPrices = $this->productListingPriceSearcher->search($criteria, $context);

        /** @var ProductBasicStruct $product */
        foreach ($productsCollection as $product) {
            if ($product->getMainDetailUuid()) {
                $product->setMainDetail($mainDetails->get($product->getMainDetailUuid()));
            }

            $product->setBlockedCustomerGroups($blockedCustomerGroups->getList($product->getBlockedCustomerGroupsUuids()));
            $product->setListingPrices($listingPrices->filterByProductUuid($product->getUuid()));
        }

        return $productsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
