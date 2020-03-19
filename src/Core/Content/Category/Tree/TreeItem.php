<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Tree;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Struct\Struct;

class TreeItem extends Struct
{
    /**
     * @var CategoryEntity
     */
    protected $category;

    /**
     * @var TreeItem[]
     */
    protected $children;

    public function __construct(?CategoryEntity $category, array $children)
    {
        $this->category = $category;
        $this->children = $children;
    }

    public function setCategory(CategoryEntity $category): void
    {
        $this->category = $category;
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
        foreach ($items as $item) {
            $this->children[] = $item;
        }
    }

    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    public function getApiAlias(): string
    {
        return 'category_tree_item';
    }
}
