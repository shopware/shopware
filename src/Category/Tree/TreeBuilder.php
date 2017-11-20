<?php declare(strict_types=1);

namespace Shopware\Category\Tree;

use Shopware\Category\Collection\CategoryBasicCollection;

class TreeBuilder
{
    /**
     * @param null|string             $parentUuid
     * @param CategoryBasicCollection $categories
     *
     * @return TreeItem[]
     */
    public static function buildTree(?string $parentUuid, CategoryBasicCollection $categories): array
    {
        $result = [];
        foreach ($categories->getElements() as $category) {
            if ($category->getParentUuid() !== $parentUuid) {
                continue;
            }

            $result[] = new TreeItem(
                $category,
                self::buildTree($category->getUuid(), $categories)
            );
        }

        return $result;
    }
}
