<?php

namespace Shopware\Product\Loader;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Reader\ProductDetailReader;
use Shopware\Product\Struct\ProductDetailStruct;
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\Search\Criteria;
use Shopware\Search\Condition\ProductUuidCondition;
use Shopware\ProductDetail\Searcher\ProductDetailSearcher;
use Shopware\ProductDetail\Struct\ProductDetailSearchResult;
use Shopware\Category\Loader\CategoryBasicLoader;
use Shopware\ProductManufacturer\Loader\ProductManufacturerBasicLoader;

class ProductDetailLoader
{
    /**
     * @var ProductDetailReader
     */
    protected $reader;
    /**
     * @var ProductDetailSearcher
     */
    private $productDetailSearcher;
    /**
     * @var CategoryBasicLoader
     */
    private $categoryBasicLoader;
    /**
     * @var ProductManufacturerBasicLoader
     */
    private $productManufacturerBasicLoader;

    public function __construct(
        ProductDetailReader $reader
        ,
        ProductDetailSearcher $productDetailSearcher,
        CategoryBasicLoader $categoryBasicLoader,
        ProductManufacturerBasicLoader $productManufacturerBasicLoader
    ) {
        $this->reader = $reader;
        $this->productDetailSearcher = $productDetailSearcher;
        $this->categoryBasicLoader = $categoryBasicLoader;
        $this->productManufacturerBasicLoader = $productManufacturerBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): ProductDetailCollection
    {
        $collection = $this->reader->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addCondition(new ProductUuidCondition($collection->getUuids()));
        /** @var ProductDetailSearchResult $productDetails */
        $productDetails = $this->productDetailSearcher->search($criteria, $context);

        $categories = $this->categoryBasicLoader->load($collection->getCategoryUuids(), $context);
        $productManufacturers = $this->productManufacturerBasicLoader->load(
            $collection->getManufacturerUuids(),
            $context
        );

        /** @var ProductDetailStruct $product */
        foreach ($collection as $product) {
            $product->setDetails($productDetails->filterByProductUuid($product->getUuid()));
            $product->setCategories($categories->getList($product->getCategoryUuids()));
            if ($product->getManufacturerUuid())) {
                $product->setManufacturer($productManufacturers->get($product->getManufacturerUuid()));
            }
        }

        return $collection;
    }
}