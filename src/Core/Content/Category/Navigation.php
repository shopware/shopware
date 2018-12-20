<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Content\Category\Util\Tree\TreeItem;

class Navigation
{
    /**
     * @var TreeItem[]
     */
    protected $tree;

    /**
     * @var CategoryEntity|null
     */
    protected $activeCategory;

    public function __construct(?CategoryEntity $activeCategory, array $tree)
    {
        $this->tree = $tree;
        $this->activeCategory = $activeCategory;
    }

    public function isCategorySelected(CategoryEntity $category): bool
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

    public function getActiveCategory(): ?CategoryEntity
    {
        return $this->activeCategory;
    }

    public function setActiveCategory(?CategoryEntity $activeCategory): void
    {
        $this->activeCategory = $activeCategory;
    }
}
