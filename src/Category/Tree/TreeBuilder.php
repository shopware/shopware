<?php declare(strict_types=1);

namespace Shopware\Category\Tree;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Category\Struct\CategoryBasicStruct;

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
        /** @var CategoryBasicStruct $category */
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
