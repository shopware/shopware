<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Util\Tree;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;

class TreeBuilder
{
    /**
     * @param null|string        $parentId
     * @param CategoryCollection $categories
     *
     * @return TreeItem[]
     */
    public static function buildTree(?string $parentId, CategoryCollection $categories): array
    {
        $result = [];
        /** @var CategoryEntity $category */
        foreach ($categories->getElements() as $category) {
            if ($category->getParentId() !== $parentId) {
                continue;
            }

            $result[] = new TreeItem(
                $category,
                self::buildTree($category->getId(), $categories)
            );
        }

        return $result;
    }
}
