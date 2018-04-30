<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Product\Collection\ProductBasicCollection;

class ProductStreamDetailStruct extends ProductStreamBasicStruct
{
    /**
     * @var string[]
     */
    protected $productIds = [];

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    public function __construct()
    {
        $this->products = new ProductBasicCollection();
    }

    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function setProductIds(array $productIds): void
    {
        $this->productIds = $productIds;
    }

    public function getProducts(): ProductBasicCollection
    {
        return $this->products;
    }

    public function setProducts(ProductBasicCollection $products): void
    {
        $this->products = $products;
    }
}
