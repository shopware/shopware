<?php declare(strict_types=1);

namespace Shopware\Product\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\DetailReaderInterface;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Category\Reader\CategoryBasicReader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Reader\CustomerGroupBasicReader;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Product\Factory\ProductDetailFactory;
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\Product\Struct\ProductDetailStruct;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearcher;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearchResult;
use Shopware\ProductMedia\Searcher\ProductMediaSearcher;
use Shopware\ProductMedia\Searcher\ProductMediaSearchResult;
use Shopware\ProductPrice\Searcher\ProductPriceSearcher;
use Shopware\ProductPrice\Searcher\ProductPriceSearchResult;
use Shopware\ProductVote\Searcher\ProductVoteSearcher;
use Shopware\ProductVote\Searcher\ProductVoteSearchResult;
use Shopware\ProductVoteAverage\Searcher\ProductVoteAverageSearcher;
use Shopware\ProductVoteAverage\Searcher\ProductVoteAverageSearchResult;

class ProductDetailReader implements DetailReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductDetailFactory
     */
    private $factory;

    /**
     * @var ProductPriceSearcher
     */
    private $productPriceSearcher;

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
        ProductPriceSearcher $productPriceSearcher,
        CustomerGroupBasicReader $customerGroupBasicReader,
        ProductListingPriceSearcher $productListingPriceSearcher,
        ProductMediaSearcher $productMediaSearcher,
        CategoryBasicReader $categoryBasicReader,
        ProductVoteSearcher $productVoteSearcher,
        ProductVoteAverageSearcher $productVoteAverageSearcher
    ) {
        $this->factory = $factory;
        $this->productPriceSearcher = $productPriceSearcher;
        $this->customerGroupBasicReader = $customerGroupBasicReader;
        $this->productListingPriceSearcher = $productListingPriceSearcher;
        $this->productMediaSearcher = $productMediaSearcher;
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

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_price.productUuid', $uuids));
        /** @var ProductPriceSearchResult $prices */
        $prices = $this->productPriceSearcher->search($criteria, $context);

        $blockedCustomerGroups = $this->customerGroupBasicReader->readBasic($productsCollection->getBlockedCustomerGroupsUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_listing_price_ro.productUuid', $uuids));
        /** @var ProductListingPriceSearchResult $listingPrices */
        $listingPrices = $this->productListingPriceSearcher->search($criteria, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_media.productUuid', $uuids));
        /** @var ProductMediaSearchResult $media */
        $media = $this->productMediaSearcher->search($criteria, $context);

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
            $product->setPrices($prices->filterByProductUuid($product->getUuid()));

            $product->setBlockedCustomerGroups($blockedCustomerGroups->getList($product->getBlockedCustomerGroupsUuids()));
            $product->setListingPrices($listingPrices->filterByProductUuid($product->getUuid()));

            $product->setMedia($media->filterByProductUuid($product->getUuid()));

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
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

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
