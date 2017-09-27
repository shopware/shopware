<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductMedia\Struct\ProductMediaBasicCollection;
use Shopware\ProductVote\Struct\ProductVoteBasicCollection;

class ProductDetailStruct extends ProductBasicStruct
{
    /**
     * @var ProductMediaBasicCollection
     */
    protected $media;

    /**
     * @var ProductDetailBasicCollection
     */
    protected $details;

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
     * @var ProductVoteBasicCollection
     */
    protected $votes;

    public function __construct()
    {
        $this->media = new ProductMediaBasicCollection();
        $this->details = new ProductDetailBasicCollection();
        $this->categories = new CategoryBasicCollection();
        $this->categoryTree = new CategoryBasicCollection();
        $this->votes = new ProductVoteBasicCollection();
    }

    public function getMedia(): ProductMediaBasicCollection
    {
        return $this->media;
    }

    public function setMedia(ProductMediaBasicCollection $media): void
    {
        $this->media = $media;
    }

    public function getDetails(): ProductDetailBasicCollection
    {
        return $this->details;
    }

    public function setDetails(ProductDetailBasicCollection $details): void
    {
        $this->details = $details;
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

    public function getVotes(): ProductVoteBasicCollection
    {
        return $this->votes;
    }

    public function setVotes(ProductVoteBasicCollection $votes): void
    {
        $this->votes = $votes;
    }
}
