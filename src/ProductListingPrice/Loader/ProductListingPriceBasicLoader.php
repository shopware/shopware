<?php

namespace Shopware\ProductListingPrice\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductListingPrice\Factory\ProductListingPriceBasicFactory;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicStruct;

class ProductListingPriceBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductListingPriceBasicFactory
     */
    private $factory;

    public function __construct(
        ProductListingPriceBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): ProductListingPriceBasicCollection
    {
        if (empty($uuids)) {
            return new ProductListingPriceBasicCollection();
        }

        $productListingPricesCollection = $this->read($uuids, $context);

        return $productListingPricesCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductListingPriceBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_listing_price_ro.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductListingPriceBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductListingPriceBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
