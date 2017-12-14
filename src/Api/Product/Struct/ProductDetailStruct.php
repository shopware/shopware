<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\Product\Collection\ProductStreamBasicCollection;
use Shopware\Api\Product\Collection\ProductTranslationBasicCollection;

class ProductDetailStruct extends ProductBasicStruct
{
    /**
     * @var ProductMediaBasicCollection
     */
    protected $media;

    /**
     * @var ProductTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var string[]
     */
    protected $categoryUuids = [];

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    /**
     * @var string[]
     */
    protected $categoryTreeUuids = [];

    /**
     * @var CategoryBasicCollection
     */
    protected $categoryTree;

    /**
     * @var string[]
     */
    protected $seoCategoryUuids = [];

    /**
     * @var CategoryBasicCollection
     */
    protected $seoCategories;

    /**
     * @var string[]
     */
    protected $tabUuids = [];

    /**
     * @var ProductStreamBasicCollection
     */
    protected $tabs;

    /**
     * @var string[]
     */
    protected $streamUuids = [];

    /**
     * @var ProductStreamBasicCollection
     */
    protected $streams;

    public function __construct()
    {
        $this->media = new ProductMediaBasicCollection();

        $this->translations = new ProductTranslationBasicCollection();

        $this->categories = new CategoryBasicCollection();

        $this->categoryTree = new CategoryBasicCollection();

        $this->seoCategories = new CategoryBasicCollection();

        $this->tabs = new ProductStreamBasicCollection();

        $this->streams = new ProductStreamBasicCollection();
    }

    public function getMedia(): ProductMediaBasicCollection
    {
        return $this->media;
    }

    public function setMedia(ProductMediaBasicCollection $media): void
    {
        $this->media = $media;
    }

    public function getTranslations(): ProductTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ProductTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCategoryUuids(): array
    {
        return $this->categoryUuids;
    }

    public function setCategoryUuids(array $categoryUuids): void
    {
        $this->categoryUuids = $categoryUuids;
    }

    public function getCategories(): CategoryBasicCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryBasicCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getCategoryTreeUuids(): array
    {
        return $this->categoryTreeUuids;
    }

    public function setCategoryTreeUuids(array $categoryTreeUuids): void
    {
        $this->categoryTreeUuids = $categoryTreeUuids;
    }

    public function getCategoryTree(): CategoryBasicCollection
    {
        return $this->categoryTree;
    }

    public function setCategoryTree(CategoryBasicCollection $categoryTree): void
    {
        $this->categoryTree = $categoryTree;
    }

    public function getSeoCategoryUuids(): array
    {
        return $this->seoCategoryUuids;
    }

    public function setSeoCategoryUuids(array $seoCategoryUuids): void
    {
        $this->seoCategoryUuids = $seoCategoryUuids;
    }

    public function getSeoCategories(): CategoryBasicCollection
    {
        return $this->seoCategories;
    }

    public function setSeoCategories(CategoryBasicCollection $seoCategories): void
    {
        $this->seoCategories = $seoCategories;
    }

    public function getTabUuids(): array
    {
        return $this->tabUuids;
    }

    public function setTabUuids(array $tabUuids): void
    {
        $this->tabUuids = $tabUuids;
    }

    public function getTabs(): ProductStreamBasicCollection
    {
        return $this->tabs;
    }

    public function setTabs(ProductStreamBasicCollection $tabs): void
    {
        $this->tabs = $tabs;
    }

    public function getStreamUuids(): array
    {
        return $this->streamUuids;
    }

    public function setStreamUuids(array $streamUuids): void
    {
        $this->streamUuids = $streamUuids;
    }

    public function getStreams(): ProductStreamBasicCollection
    {
        return $this->streams;
    }

    public function setStreams(ProductStreamBasicCollection $streams): void
    {
        $this->streams = $streams;
    }
}
