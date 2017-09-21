<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Struct;

use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;

class ProductDetailDetailStruct extends ProductDetailBasicStruct
{
    /**
     * @var ProductDetailPriceBasicCollection
     */
    protected $prices;

    public function __construct()
    {
        $this->prices = new ProductDetailPriceBasicCollection();
    }

    public function getPrices(): ProductDetailPriceBasicCollection
    {
        return $this->prices;
    }

    public function setPrices(ProductDetailPriceBasicCollection $prices): void
    {
        $this->prices = $prices;
    }
}
