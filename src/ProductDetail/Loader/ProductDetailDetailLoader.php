<?php

namespace Shopware\ProductDetail\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductDetail\Factory\ProductDetailDetailFactory;
use Shopware\ProductDetail\Struct\ProductDetailDetailCollection;
use Shopware\ProductDetail\Struct\ProductDetailDetailStruct;
use Shopware\ProductPrice\Searcher\ProductPriceSearcher;
use Shopware\ProductPrice\Searcher\ProductPriceSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class ProductDetailDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductDetailDetailFactory
     */
    private $factory;

    /**
     * @var ProductPriceSearcher
     */
    private $productPriceSearcher;

    public function __construct(
        ProductDetailDetailFactory $factory,
        ProductPriceSearcher $productPriceSearcher
    ) {
        $this->factory = $factory;
        $this->productPriceSearcher = $productPriceSearcher;
    }

    public function load(array $uuids, TranslationContext $context): ProductDetailDetailCollection
    {
        if (empty($uuids)) {
            return new ProductDetailDetailCollection();
        }

        $productDetailsCollection = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_price.product_detail_uuid', $uuids));
        /** @var ProductPriceSearchResult $prices */
        $prices = $this->productPriceSearcher->search($criteria, $context);

        /** @var ProductDetailDetailStruct $productDetail */
        foreach ($productDetailsCollection as $productDetail) {
            $productDetail->setPrices($prices->filterByProductDetailUuid($productDetail->getUuid()));
        }

        return $productDetailsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductDetailDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_detail.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductDetailDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductDetailDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
