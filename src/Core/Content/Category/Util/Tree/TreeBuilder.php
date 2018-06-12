<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Util\Tree;

use Shopware\Core\Content\Category\CategoryBasicCollection;
use Shopware\Core\Content\Category\CategoryBasicStruct;

class TreeBuilder
{
    /**
     * @param null|string             $parentId
     * @param \Shopware\Core\Content\Category\CategoryBasicCollection $categories
     *
     * @return TreeItem[]
     */
    public static function buildTree(?string $parentId, CategoryBasicCollection $categories): array
    {
        $result = [];
        /** @var \Shopware\Core\Content\Category\CategoryBasicStruct $category */
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
