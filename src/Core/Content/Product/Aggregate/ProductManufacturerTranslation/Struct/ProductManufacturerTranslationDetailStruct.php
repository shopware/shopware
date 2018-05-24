<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerBasicStruct;

class ProductManufacturerTranslationDetailStruct extends ProductManufacturerTranslationBasicStruct
{
    /**
     * @var ProductManufacturerBasicStruct
     */
    protected $productManufacturer;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
