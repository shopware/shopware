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
use Shopware\ProductDetail\Loader\ProductDetailDetailLoader;
use Shopware\ProductDetail\Searcher\ProductDetailSearcher;
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
     * @var CustomerGroupBasicLoader
     */
    private $customerGroupBasicLoader;

    /**
     * @var ProductDetailSearcher
     */
    private $productDetailSearcher;

    /**
     * @var ProductDetailDetailLoader
     */
    private $productDetailDetailLoader;

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
        CustomerGroupBasicLoader $customerGroupBasicLoader,
        ProductDetailSearcher $productDetailSearcher,
        ProductDetailDetailLoader $productDetailDetailLoader,
        CategoryBasicLoader $categoryBasicLoader,
        ProductVoteSearcher $productVoteSearcher
    ) {
        $this->factory = $factory;
        $this->customerGroupBasicLoader = $customerGroupBasicLoader;
        $this->productDetailSearcher = $productDetailSearcher;
        $this->productDetailDetailLoader = $productDetailDetailLoader;
        $this->categoryBasicLoader = $categoryBasicLoader;
        $this->productVoteSearcher = $productVoteSearcher;
    }

    public function load(array $uuids, TranslationContext $context): ProductDetailCollection
    {
        if (empty($uuids)) {
            return new ProductDetailCollection();
        }

        $productsCollection = $this->read($uuids, $context);

        $blockedCustomerGroups = $this->customerGroupBasicLoader->load($productsCollection->getBlockedCustomerGroupsUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_detail.product_uuid', $uuids));
        $detailsUuids = $this->productDetailSearcher->searchUuids($criteria, $context);
        $details = $this->productDetailDetailLoader->load($detailsUuids->getUuids(), $context);

        $categories = $this->categoryBasicLoader->load($productsCollection->getCategoryUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_vote.product_uuid', $uuids));
        /** @var ProductVoteSearchResult $votes */
        $votes = $this->productVoteSearcher->search($criteria, $context);

        /** @var ProductDetailStruct $product */
        foreach ($productsCollection as $product) {
            $product->setBlockedCustomerGroups($blockedCustomerGroups->getList($product->getBlockedCustomerGroupsUuids()));
            $product->setDetails($details->filterByProductUuid($product->getUuid()));

            $product->setCategories($categories->getList($product->getCategoryUuids()));
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
