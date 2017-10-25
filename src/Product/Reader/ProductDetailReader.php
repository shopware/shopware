<?php declare(strict_types=1);

namespace Shopware\Product\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Category\Reader\CategoryBasicReader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Reader\CustomerGroupBasicReader;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Product\Factory\ProductDetailFactory;
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\Product\Struct\ProductDetailStruct;
use Shopware\ProductDetail\Reader\ProductDetailBasicReader;
use Shopware\ProductDetail\Searcher\ProductDetailSearcher;
use Shopware\ProductDetail\Searcher\ProductDetailSearchResult;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearcher;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearchResult;
use Shopware\ProductMedia\Searcher\ProductMediaSearcher;
use Shopware\ProductMedia\Searcher\ProductMediaSearchResult;
use Shopware\ProductVote\Searcher\ProductVoteSearcher;
use Shopware\ProductVote\Searcher\ProductVoteSearchResult;
use Shopware\ProductVoteAverage\Searcher\ProductVoteAverageSearcher;
use Shopware\ProductVoteAverage\Searcher\ProductVoteAverageSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class ProductDetailReader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductDetailFactory
     */
    private $factory;

    /**
     * @var ProductDetailBasicReader
     */
    private $productDetailBasicReader;

    /**
     * @var CustomerGroupBasicReader
     */
    private $customerGroupBasicReader;

    /**
     * @var ProductListingPriceSearcher
     */
    private $productListingPriceSearcher;

    /**
     * @var ProductMediaSearcher
     */
    private $productMediaSearcher;

    /**
     * @var ProductDetailSearcher
     */
    private $productDetailSearcher;

    /**
     * @var CategoryBasicReader
     */
    private $categoryBasicReader;

    /**
     * @var ProductVoteSearcher
     */
    private $productVoteSearcher;

    /**
     * @var ProductVoteAverageSearcher
     */
    private $productVoteAverageSearcher;

    public function __construct(
        ProductDetailFactory $factory,
        ProductDetailBasicReader $productDetailBasicReader,
        CustomerGroupBasicReader $customerGroupBasicReader,
        ProductListingPriceSearcher $productListingPriceSearcher,
        ProductMediaSearcher $productMediaSearcher,
        ProductDetailSearcher $productDetailSearcher,
        CategoryBasicReader $categoryBasicReader,
        ProductVoteSearcher $productVoteSearcher,
        ProductVoteAverageSearcher $productVoteAverageSearcher
    ) {
        $this->factory = $factory;
        $this->productDetailBasicReader = $productDetailBasicReader;
        $this->customerGroupBasicReader = $customerGroupBasicReader;
        $this->productListingPriceSearcher = $productListingPriceSearcher;
        $this->productMediaSearcher = $productMediaSearcher;
        $this->productDetailSearcher = $productDetailSearcher;
        $this->categoryBasicReader = $categoryBasicReader;
        $this->productVoteSearcher = $productVoteSearcher;
        $this->productVoteAverageSearcher = $productVoteAverageSearcher;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductDetailCollection
    {
        if (empty($uuids)) {
            return new ProductDetailCollection();
        }

        $productsCollection = $this->read($uuids, $context);

        $mainDetails = $this->productDetailBasicReader->readBasic($productsCollection->getMainDetailUuids(), $context);

        $blockedCustomerGroups = $this->customerGroupBasicReader->readBasic($productsCollection->getBlockedCustomerGroupsUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_listing_price_ro.productUuid', $uuids));
        /** @var ProductListingPriceSearchResult $listingPrices */
        $listingPrices = $this->productListingPriceSearcher->search($criteria, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_media.productUuid', $uuids));
        /** @var ProductMediaSearchResult $media */
        $media = $this->productMediaSearcher->search($criteria, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_detail.productUuid', $uuids));
        /** @var ProductDetailSearchResult $details */
        $details = $this->productDetailSearcher->search($criteria, $context);

        $categoryTree = $this->categoryBasicReader->readBasic($productsCollection->getCategoryTreeUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_vote.productUuid', $uuids));
        /** @var ProductVoteSearchResult $votes */
        $votes = $this->productVoteSearcher->search($criteria, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_vote_average_ro.productUuid', $uuids));
        /** @var ProductVoteAverageSearchResult $voteAverages */
        $voteAverages = $this->productVoteAverageSearcher->search($criteria, $context);

        /** @var ProductDetailStruct $product */
        foreach ($productsCollection as $product) {
            if ($product->getMainDetailUuid()) {
                $product->setMainDetail($mainDetails->get($product->getMainDetailUuid()));
            }

            $product->setBlockedCustomerGroups($blockedCustomerGroups->getList($product->getBlockedCustomerGroupsUuids()));
            $product->setListingPrices($listingPrices->filterByProductUuid($product->getUuid()));

            $product->setMedia($media->filterByProductUuid($product->getUuid()));

            $product->setDetails($details->filterByProductUuid($product->getUuid()));

            $product->setCategories($categoryTree->getList($product->getCategoryUuids()));
            $product->setCategoryTree($categoryTree->getList($product->getCategoryTreeUuids()));
            $product->setVotes($votes->filterByProductUuid($product->getUuid()));

            $product->setVoteAverages($voteAverages->filterByProductUuid($product->getUuid()));
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
