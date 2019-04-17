<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Struct\Struct;

class ProductSliderStruct extends Struct
{
    /**
     * @var ProductCollection|null
     */
    protected $products;

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }
}
