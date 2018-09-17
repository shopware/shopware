<?php declare(strict_types=1);

namespace Shopware\Storefront\Navigation;

use Shopware\Core\Content\Category\CategoryStruct;
use Shopware\Core\Content\Category\Util\Tree\TreeItem;

class Navigation
{
    /**
     * @var TreeItem[]
     */
    protected $tree;

    /**
     * @var CategoryStruct
     */
    protected $activeCategory;

    public function __construct(?CategoryStruct $activeCategory, array $tree)
    {
        $this->tree = $tree;
        $this->activeCategory = $activeCategory;
    }

    public function isCategorySelected(CategoryStruct $category): bool
    {
        if (!$this->activeCategory) {
            return false;
        }

        if ($category->getId() === $this->activeCategory->getId()) {
            return true;
        }

        return \in_array($category->getId(), $this->activeCategory->getPathArray(), true);
    }

    public function getTree(): array
    {
        return $this->tree;
    }

    public function setTree(array $tree): void
    {
        $this->tree = $tree;
    }

    public function getActiveCategory(): ?CategoryStruct
    {
        return $this->activeCategory;
    }

    public function setActiveCategory(?CategoryStruct $activeCategory): void
    {
        $this->activeCategory = $activeCategory;
    }
}
