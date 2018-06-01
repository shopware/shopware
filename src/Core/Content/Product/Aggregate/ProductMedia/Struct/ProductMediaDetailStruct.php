<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia\Struct;

use Shopware\Core\Content\Product\Struct\ProductBasicStruct;

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
