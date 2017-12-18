<?php declare(strict_types=1);

namespace Shopware\Api\Category\Struct;

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
    protected $productUuids = [];

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    /**
     * @var string[]
     */
    protected $seoProductUuids = [];

    /**
     * @var ProductBasicCollection
     */
    protected $seoProducts;

    public function __construct()
    {
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

    public function getSeoProductUuids(): array
    {
        return $this->seoProductUuids;
    }

    public function setSeoProductUuids(array $seoProductUuids): void
    {
        $this->seoProductUuids = $seoProductUuids;
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
