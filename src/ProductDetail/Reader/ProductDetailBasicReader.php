<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductDetail\Factory\ProductDetailBasicFactory;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;
use Shopware\ProductDetailPrice\Searcher\ProductDetailPriceSearcher;
use Shopware\ProductDetailPrice\Searcher\ProductDetailPriceSearchResult;

class ProductDetailBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductDetailBasicFactory
     */
    private $factory;

    /**
     * @var ProductDetailPriceSearcher
     */
    private $productDetailPriceSearcher;

    public function __construct(
        ProductDetailBasicFactory $factory,
        ProductDetailPriceSearcher $productDetailPriceSearcher
    ) {
        $this->factory = $factory;
        $this->productDetailPriceSearcher = $productDetailPriceSearcher;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductDetailBasicCollection
    {
        if (empty($uuids)) {
            return new ProductDetailBasicCollection();
        }

        $productDetailsCollection = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_detail_price.productDetailUuid', $uuids));
        /** @var ProductDetailPriceSearchResult $prices */
        $prices = $this->productDetailPriceSearcher->search($criteria, $context);

        /** @var ProductDetailBasicStruct $productDetail */
        foreach ($productDetailsCollection as $productDetail) {
            $productDetail->setPrices($prices->filterByProductDetailUuid($productDetail->getUuid()));
        }

        return $productDetailsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductDetailBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_detail.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductDetailBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductDetailBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
