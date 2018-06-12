<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Util\Tree;

use Shopware\Core\Content\Category\CategoryBasicStruct;

class TreeItem
{
    /**
     * @var \Shopware\Core\Content\Category\CategoryBasicStruct
     */
    protected $category;

    /**
     * @var \Shopware\Core\Content\Category\CategoryBasicStruct[]
     */
    protected $children;

    public function __construct(CategoryBasicStruct $category, array $children)
    {
        $this->category = $category;
        $this->children = $children;
    }

    public function getCategory(): CategoryBasicStruct
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
