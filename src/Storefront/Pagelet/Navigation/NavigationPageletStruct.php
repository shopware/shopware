<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Navigation;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Util\Tree\TreeItem;
use Shopware\Core\Framework\Struct\Struct;

class NavigationPageletStruct extends Struct
{
    /**
     * @var TreeItem[]
     */
    protected $tree;

    /**
     * @var CategoryEntity|null
     */
    protected $activeCategory;

    /**
     * @return TreeItem[]
     */
    public function getTree(): array
    {
        return $this->tree;
    }

    /**
     * @param TreeItem[] $tree
     */
    public function setTree(array $tree): void
    {
        $this->tree = $tree;
    }

    /**
     * @return CategoryEntity|null
     */
    public function getActiveCategory(): ?CategoryEntity
    {
        return $this->activeCategory;
    }

    /**
     * @param CategoryEntity|null $activeCategory
     */
    public function setActiveCategory(?CategoryEntity $activeCategory): void
    {
        $this->activeCategory = $activeCategory;
    }
}
