<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Util\Tree;

use Shopware\Core\Content\Category\CategoryStruct;

class TreeItem
{
    /**
     * @var CategoryStruct
     */
    protected $category;

    /**
     * @var CategoryStruct[]
     */
    protected $children;

    public function __construct(CategoryStruct $category, array $children)
    {
        $this->category = $category;
        $this->children = $children;
    }

    public function getCategory(): CategoryStruct
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
