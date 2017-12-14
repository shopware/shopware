<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductManufacturerTranslationBasicCollection;

class ProductManufacturerDetailStruct extends ProductManufacturerBasicStruct
{
    /**
     * @var ProductBasicCollection
     */
    protected $products;

    /**
     * @var ProductManufacturerTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->products = new ProductBasicCollection();

        $this->translations = new ProductManufacturerTranslationBasicCollection();
    }

    public function getProducts(): ProductBasicCollection
    {
        return $this->products;
    }

    public function setProducts(ProductBasicCollection $products): void
    {
        $this->products = $products;
    }

    public function getTranslations(): ProductManufacturerTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ProductManufacturerTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
