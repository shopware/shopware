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
     * @var ProductBasicCollection
     */
    protected $products;

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

    public function getProducts(): ProductBasicCollection
    {
        return $this->products;
    }

    public function setProducts(ProductBasicCollection $products): void
    {
        $this->products = $products;
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
