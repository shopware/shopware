<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation\Struct;

use Shopware\Core\Content\Product\Struct\ProductBasicStruct;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;

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
