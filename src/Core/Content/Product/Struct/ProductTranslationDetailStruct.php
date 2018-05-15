<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Api\Language\Struct\LanguageBasicStruct;

class ProductTranslationDetailStruct extends ProductTranslationBasicStruct
{
    /**
     * @var ProductBasicStruct
     */
    protected $product;

    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    public function getProduct(): ProductBasicStruct
    {
        return $this->product;
    }

    public function setProduct(ProductBasicStruct $product): void
    {
        $this->product = $product;
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
