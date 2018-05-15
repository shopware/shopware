<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\System\Configuration\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductConfiguratorBasicCollection;
use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\Product\Collection\ProductSearchKeywordBasicCollection;
use Shopware\Api\Product\Collection\ProductServiceBasicCollection;
use Shopware\Api\Product\Collection\ProductStreamBasicCollection;
use Shopware\Api\Product\Collection\ProductTranslationBasicCollection;

class ProductDetailStruct extends ProductBasicStruct
{
    /**
     * @var ProductBasicStruct|null
     */
    protected $parent;

    /**
     * @var ProductBasicCollection
     */
    protected $children;

    /**
     * @var ProductMediaBasicCollection
     */
    protected $media;

    /**
     * @var ProductSearchKeywordBasicCollection
     */
    protected $searchKeywords;

    /**
     * @var ProductTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    /**
     * @var CategoryBasicCollection
     */
    protected $seoCategories;

    /**
     * @var ProductStreamBasicCollection
     */
    protected $tabs;

    /**
     * @var ProductStreamBasicCollection
     */
    protected $streams;

    /**
     * @var ConfigurationGroupOptionBasicCollection
     */
    protected $datasheet;

    /**
     * @var ConfigurationGroupOptionBasicCollection
     */
    protected $variations;

    /**
     * @var ProductConfiguratorBasicCollection
     */
    protected $configurators;

    /**
     * @var ProductServiceBasicCollection
     */
    protected $services;

    public function __construct()
    {
        $this->children = new ProductBasicCollection();
        $this->media = new ProductMediaBasicCollection();
        $this->searchKeywords = new ProductSearchKeywordBasicCollection();
        $this->translations = new ProductTranslationBasicCollection();
        $this->categories = new CategoryBasicCollection();
        $this->seoCategories = new CategoryBasicCollection();
        $this->tabs = new ProductStreamBasicCollection();
        $this->streams = new ProductStreamBasicCollection();
        $this->configurators = new ProductConfiguratorBasicCollection();
        $this->services = new ProductServiceBasicCollection();
        $this->datasheet = new ConfigurationGroupOptionBasicCollection();
        $this->variations = new ConfigurationGroupOptionBasicCollection();
    }

    public function getParent(): ?ProductBasicStruct
    {
        return $this->parent;
    }

    public function setParent(?ProductBasicStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ProductBasicCollection
    {
        return $this->children;
    }

    public function setChildren(ProductBasicCollection $children): void
    {
        $this->children = $children;
    }

    public function getMedia(): ProductMediaBasicCollection
    {
        return $this->media;
    }

    public function setMedia(ProductMediaBasicCollection $media): void
    {
        $this->media = $media;
    }

    public function getSearchKeywords(): ProductSearchKeywordBasicCollection
    {
        return $this->searchKeywords;
    }

    public function setSearchKeywords(ProductSearchKeywordBasicCollection $searchKeywords): void
    {
        $this->searchKeywords = $searchKeywords;
    }

    public function getTranslations(): ProductTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ProductTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCategories(): CategoryBasicCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryBasicCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getSeoCategories(): CategoryBasicCollection
    {
        return $this->seoCategories;
    }

    public function setSeoCategories(CategoryBasicCollection $seoCategories): void
    {
        $this->seoCategories = $seoCategories;
    }

    public function getTabs(): ProductStreamBasicCollection
    {
        return $this->tabs;
    }

    public function setTabs(ProductStreamBasicCollection $tabs): void
    {
        $this->tabs = $tabs;
    }

    public function getStreams(): ProductStreamBasicCollection
    {
        return $this->streams;
    }

    public function setStreams(ProductStreamBasicCollection $streams): void
    {
        $this->streams = $streams;
    }

    public function getConfigurators(): ProductConfiguratorBasicCollection
    {
        return $this->configurators;
    }

    public function setConfigurators(ProductConfiguratorBasicCollection $configurators): void
    {
        $this->configurators = $configurators;
    }

    public function getServices(): ProductServiceBasicCollection
    {
        return $this->services;
    }

    public function setServices(ProductServiceBasicCollection $services): void
    {
        $this->services = $services;
    }

    public function getDatasheet(): ConfigurationGroupOptionBasicCollection
    {
        return $this->datasheet;
    }

    public function setDatasheet(ConfigurationGroupOptionBasicCollection $datasheet): void
    {
        $this->datasheet = $datasheet;
    }

    public function getVariations(): ConfigurationGroupOptionBasicCollection
    {
        return $this->variations;
    }

    public function setVariations(ConfigurationGroupOptionBasicCollection $variations): void
    {
        $this->variations = $variations;
    }
}
