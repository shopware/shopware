<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Product\Collection\ProductBasicCollection;

class ProductStreamDetailStruct extends ProductStreamBasicStruct
{
    /**
     * @var string[]
     */
    protected $productTabUuids = [];

    /**
     * @var ProductBasicCollection
     */
    protected $productTabs;

    /**
     * @var string[]
     */
    protected $productUuids = [];

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    public function __construct()
    {
        $this->productTabs = new ProductBasicCollection();

        $this->products = new ProductBasicCollection();
    }

    public function getProductTabUuids(): array
    {
        return $this->productTabUuids;
    }

    public function setProductTabUuids(array $productTabUuids): void
    {
        $this->productTabUuids = $productTabUuids;
    }

    public function getProductTabs(): ProductBasicCollection
    {
        return $this->productTabs;
    }

    public function setProductTabs(ProductBasicCollection $productTabs): void
    {
        $this->productTabs = $productTabs;
    }

    public function getProductUuids(): array
    {
        return $this->productUuids;
    }

    public function setProductUuids(array $productUuids): void
    {
        $this->productUuids = $productUuids;
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
