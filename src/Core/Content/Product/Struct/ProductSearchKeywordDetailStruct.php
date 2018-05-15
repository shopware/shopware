<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class ProductSearchKeywordDetailStruct extends ProductSearchKeywordBasicStruct
{
    /**
     * @var ShopBasicStruct
     */
    protected $shop;

    /**
     * @var ProductBasicStruct
     */
    protected $product;

    public function getShop(): ShopBasicStruct
    {
        return $this->shop;
    }

    public function setShop(ShopBasicStruct $shop): void
    {
        $this->shop = $shop;
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
