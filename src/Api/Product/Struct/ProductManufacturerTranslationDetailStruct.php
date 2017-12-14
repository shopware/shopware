<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class ProductManufacturerTranslationDetailStruct extends ProductManufacturerTranslationBasicStruct
{
    /**
     * @var ProductManufacturerBasicStruct
     */
    protected $productManufacturer;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getProductManufacturer(): ProductManufacturerBasicStruct
    {
        return $this->productManufacturer;
    }

    public function setProductManufacturer(ProductManufacturerBasicStruct $productManufacturer): void
    {
        $this->productManufacturer = $productManufacturer;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
