<?php

namespace Shopware\Storefront\Navigation;

use Shopware\Category\Struct\Category;
use Shopware\Category\Struct\CategoryIdentity;

class Navigation
{
    /**
     * @var CategoryIdentity[]
     */
    protected $tree;

    /**
     * @var Category
     */
    protected $activeCategory;

    public function __construct(Category $activeCategory, array $tree)
    {
        $this->tree = $tree;
        $this->activeCategory = $activeCategory;
    }

    public function getTree(): array
    {
        return $this->tree;
    }

    public function setTree(array $tree): void
    {
        $this->tree = $tree;
    }

    public function getActiveCategory(): Category
    {
        return $this->activeCategory;
    }

    public function setActiveCategory(Category $activeCategory): void
    {
        $this->activeCategory = $activeCategory;
    }
}