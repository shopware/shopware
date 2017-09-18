<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Struct;

use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;

class ProductDetailDetailStruct extends ProductDetailBasicStruct
{
    /**
     * @var string[]
     */
    protected $priceUuids = [];

    /**
     * @var ProductPriceBasicCollection
     */
    protected $prices;

    public function __construct()
    {
        $this->prices = new ProductPriceBasicCollection();
    }

    public function getPriceUuids(): array
    {
        return $this->priceUuids;
    }

    public function setPriceUuids(array $priceUuids): void
    {
        $this->priceUuids = $priceUuids;
    }

    public function getPrices(): ProductPriceBasicCollection
    {
        return $this->prices;
    }

    public function setPrices(ProductPriceBasicCollection $prices): void
    {
        $this->prices = $prices;
    }
}
