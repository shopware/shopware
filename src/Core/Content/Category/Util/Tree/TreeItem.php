<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Util\Tree;

use Shopware\Core\Content\Category\CategoryEntity;

class TreeItem
{
    /**
     * @var CategoryEntity
     */
    protected $category;

    /**
     * @var TreeItem[]
     */
    protected $children;

    public function __construct(CategoryEntity $category, array $children)
    {
        $this->category = $category;
        $this->children = $children;
    }

    public function getCategory(): CategoryEntity
    {
        return $this->category;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChildren(TreeItem ...$items): void
    {
        $this->children = array_merge($this->children, $items);
    }
}
