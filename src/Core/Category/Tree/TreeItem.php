<?php declare(strict_types=1);

namespace Shopware\Category\Tree;

use Shopware\Api\Category\Struct\CategoryBasicStruct;

class TreeItem
{
    /**
     * @var CategoryBasicStruct
     */
    protected $category;

    /**
     * @var CategoryBasicStruct[]
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
