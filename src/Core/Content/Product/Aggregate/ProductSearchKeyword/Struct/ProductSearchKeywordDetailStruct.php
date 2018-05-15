<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductSearchKeyword\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;

use Shopware\Content\Product\Struct\ProductBasicStruct;

class ProductSearchKeywordDetailStruct extends ProductSearchKeywordBasicStruct
{
    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    /**
     * @var ProductBasicStruct
     */
    protected $product;

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }

    public function getProduct(): ProductBasicStruct
    {
        return $this->product;
    }

    public function setProduct(ProductBasicStruct $product): void
    {
        $this->product = $product;
    }
}
