<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class ProductTranslationDetailStruct extends ProductTranslationBasicStruct
{
    /**
     * @var ProductBasicStruct
     */
    protected $product;

    /**
     * @var ShopBasicStruct
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

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
