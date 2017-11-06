<?php declare(strict_types=1);

namespace Shopware\Product\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Reader\CustomerGroupBasicReader;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Product\Factory\ProductBasicFactory;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearcher;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearchResult;
use Shopware\ProductPrice\Searcher\ProductPriceSearcher;
use Shopware\ProductPrice\Searcher\ProductPriceSearchResult;

class ProductBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductBasicFactory
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

    public function __construct(
        ProductBasicFactory $factory,
        ProductPriceSearcher $productPriceSearcher,
        CustomerGroupBasicReader $customerGroupBasicReader,
        ProductListingPriceSearcher $productListingPriceSearcher
    ) {
        $this->factory = $factory;
        $this->productPriceSearcher = $productPriceSearcher;
        $this->customerGroupBasicReader = $customerGroupBasicReader;
        $this->productListingPriceSearcher = $productListingPriceSearcher;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductBasicCollection
    {
        if (empty($uuids)) {
            return new ProductBasicCollection();
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

        /** @var ProductBasicStruct $product */
        foreach ($productsCollection as $product) {
            $product->setPrices($prices->filterByProductUuid($product->getUuid()));

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
