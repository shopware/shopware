<?php declare(strict_types=1);

namespace Shopware\Api\Category\Struct;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Category\Collection\CategoryTranslationBasicCollection;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class CategoryDetailStruct extends CategoryBasicStruct
{
    /**
     * @var CategoryBasicStruct|null
     */
    protected $parent;

    /**
     * @var CategoryBasicCollection
     */
    protected $children;

    /**
     * @var CategoryTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    /**
     * @var string[]
     */
    protected $productIds = [];

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    /**
     * @var string[]
     */
    protected $seoProductIds = [];

    /**
     * @var ProductBasicCollection
     */
    protected $seoProducts;

    public function __construct()
    {
        $this->children = new CategoryBasicCollection();

        $this->translations = new CategoryTranslationBasicCollection();

        $this->shops = new ShopBasicCollection();

        $this->products = new ProductBasicCollection();

        $this->seoProducts = new ProductBasicCollection();
    }

    public function getParent(): ?CategoryBasicStruct
    {
        return $this->parent;
    }

    public function setParent(?CategoryBasicStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): CategoryBasicCollection
    {
        return $this->children;
    }

    public function setChildren(CategoryBasicCollection $children): void
    {
        $this->children = $children;
    }

    public function getTranslations(): CategoryTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CategoryTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function setShops(ShopBasicCollection $shops): void
    {
        $this->shops = $shops;
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

    public function getSeoProductIds(): array
    {
        return $this->seoProductIds;
    }

    public function setSeoProductIds(array $seoProductIds): void
    {
        $this->seoProductIds = $seoProductIds;
    }

    public function getSeoProducts(): ProductBasicCollection
    {
        return $this->seoProducts;
    }

    public function setSeoProducts(ProductBasicCollection $seoProducts): void
    {
        $this->seoProducts = $seoProducts;
    }
}
