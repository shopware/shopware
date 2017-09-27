<?php

namespace Shopware\Product\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Category\Loader\CategoryBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Loader\CustomerGroupBasicLoader;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Product\Factory\ProductDetailFactory;
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\Product\Struct\ProductDetailStruct;
use Shopware\ProductDetail\Loader\ProductDetailBasicLoader;
use Shopware\ProductDetail\Searcher\ProductDetailSearcher;
use Shopware\ProductDetail\Searcher\ProductDetailSearchResult;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearcher;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearchResult;
use Shopware\ProductVote\Searcher\ProductVoteSearcher;
use Shopware\ProductVote\Searcher\ProductVoteSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class ProductDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductDetailFactory
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

    /**
     * @var ProductDetailSearcher
     */
    private $productDetailSearcher;

    /**
     * @var CategoryBasicLoader
     */
    private $categoryBasicLoader;

    /**
     * @var ProductVoteSearcher
     */
    private $productVoteSearcher;

    public function __construct(
        ProductDetailFactory $factory,
        ProductDetailBasicLoader $productDetailBasicLoader,
        CustomerGroupBasicLoader $customerGroupBasicLoader,
        ProductListingPriceSearcher $productListingPriceSearcher,
        ProductDetailSearcher $productDetailSearcher,
        CategoryBasicLoader $categoryBasicLoader,
        ProductVoteSearcher $productVoteSearcher
    ) {
        $this->factory = $factory;
        $this->productDetailBasicLoader = $productDetailBasicLoader;
        $this->customerGroupBasicLoader = $customerGroupBasicLoader;
        $this->productListingPriceSearcher = $productListingPriceSearcher;
        $this->productDetailSearcher = $productDetailSearcher;
        $this->categoryBasicLoader = $categoryBasicLoader;
        $this->productVoteSearcher = $productVoteSearcher;
    }

    public function load(array $uuids, TranslationContext $context): ProductDetailCollection
    {
        if (empty($uuids)) {
            return new ProductDetailCollection();
        }

        $productsCollection = $this->read($uuids, $context);

        $mainDetails = $this->productDetailBasicLoader->load($productsCollection->getMainDetailUuids(), $context);

        $blockedCustomerGroups = $this->customerGroupBasicLoader->load($productsCollection->getBlockedCustomerGroupsUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_listing_price_ro.product_uuid', $uuids));
        /** @var ProductListingPriceSearchResult $listingPrices */
        $listingPrices = $this->productListingPriceSearcher->search($criteria, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_detail.product_uuid', $uuids));
        /** @var ProductDetailSearchResult $details */
        $details = $this->productDetailSearcher->search($criteria, $context);

        $categories = $this->categoryBasicLoader->load($productsCollection->getCategoryUuids(), $context);

        $categoryTree = $this->categoryBasicLoader->load($productsCollection->getCategoryTreeUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_vote.product_uuid', $uuids));
        /** @var ProductVoteSearchResult $votes */
        $votes = $this->productVoteSearcher->search($criteria, $context);

        /** @var ProductDetailStruct $product */
        foreach ($productsCollection as $product) {
            if ($product->getMainDetailUuid()) {
                $product->setMainDetail($mainDetails->get($product->getMainDetailUuid()));
            }

            $product->setBlockedCustomerGroups($blockedCustomerGroups->getList($product->getBlockedCustomerGroupsUuids()));
            $product->setListingPrices($listingPrices->filterByProductUuid($product->getUuid()));

            $product->setDetails($details->filterByProductUuid($product->getUuid()));

            $product->setCategories($categories->getList($product->getCategoryUuids()));
            $product->setCategoryTree($categoryTree->getList($product->getCategoryTreeUuids()));
            $product->setVotes($votes->filterByProductUuid($product->getUuid()));
        }

        return $productsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
