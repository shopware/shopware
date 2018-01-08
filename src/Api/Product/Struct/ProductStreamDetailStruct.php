<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Product\Collection\ProductBasicCollection;

class ProductStreamDetailStruct extends ProductStreamBasicStruct
{
    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    /**
     * @var string[]
     */
    protected $productTabIds = [];

    /**
     * @var ProductBasicCollection
     */
    protected $productTabs;

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
        $this->categories = new CategoryBasicCollection();

        $this->productTabs = new ProductBasicCollection();

        $this->products = new ProductBasicCollection();
    }

    public function getCategories(): CategoryBasicCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryBasicCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getProductTabIds(): array
    {
        return $this->productTabIds;
    }

    public function setProductTabIds(array $productTabIds): void
    {
        $this->productTabIds = $productTabIds;
    }

    public function getProductTabs(): ProductBasicCollection
    {
        return $this->productTabs;
    }

    public function setProductTabs(ProductBasicCollection $productTabs): void
    {
        $this->productTabs = $productTabs;
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
