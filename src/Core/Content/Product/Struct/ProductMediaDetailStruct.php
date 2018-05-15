<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

class ProductMediaDetailStruct extends ProductMediaBasicStruct
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
