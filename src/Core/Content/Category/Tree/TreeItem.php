<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Tree;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('content')]
class TreeItem extends Struct
{
    /**
     * @internal public to allow AfterSort::sort()
     */
    public ?string $afterId;

    /**
     * @var CategoryEntity
     */
    protected $category;

    /**
     * @var TreeItem[]
     */
    protected $children;

    public function __construct(
        ?CategoryEntity $category,
        array $children
    ) {
        $this->category = $category;
        $this->children = $children;
        $this->afterId = $category ? $category->getAfterCategoryId() : null;
    }

    public function getId(): string
    {
        return $this->category->getId();
    }

    public function setCategory(CategoryEntity $category): void
    {
        $this->category = $category;
        $this->afterId = $category->getAfterCategoryId();
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
