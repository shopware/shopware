<?php

namespace Shopware\Product\Loader;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Reader\ProductBasicReader;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\ProductManufacturer\Loader\ProductManufacturerBasicLoader;

class ProductBasicLoader
{
    /**
     * @var ProductBasicReader
     */
    protected $reader;

    /**
     * @var ProductManufacturerBasicLoader
     */
    private $productManufacturerBasicLoader;

    public function __construct(
        ProductBasicReader $reader,
        ProductManufacturerBasicLoader $productManufacturerBasicLoader
    ) {
        $this->reader = $reader;
        $this->productManufacturerBasicLoader = $productManufacturerBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): ProductBasicCollection
    {
        if (empty($uuids)) {
            return new ProductBasicCollection();
        }

        $collection = $this->reader->read($uuids, $context);
        $productManufacturers = $this->productManufacturerBasicLoader->load(
            $collection->getManufacturerUuids(),
            $context
        );
        foreach ($collection as $product) {
            if ($product->getManufacturerUuid())) {
                $product->setManufacturer($productManufacturers->get($product->getManufacturerUuid()));
            }
        }

        return $collection;
    }
}