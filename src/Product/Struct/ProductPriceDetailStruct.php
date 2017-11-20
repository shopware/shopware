<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

class ProductPriceDetailStruct extends ProductPriceBasicStruct
{
    /**
     * @var ProductBasicStruct
     */
    protected $product;

    public function getProduct(): ProductBasicStruct
    {
        return $this->product;
    }

    public function setProduct(ProductBasicStruct $product): void
    {
        $this->product = $product;
    }
}
