<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Storefront\Navigation;

use Shopware\Core\Content\Category\CategoryStruct;
use Shopware\Core\Content\Category\Util\Tree\TreeItem;

class Navigation
{
    /**
     * @var TreeItem[]
     */
    protected $tree;

    /**
     * @var CategoryStruct
     */
    protected $activeCategory;

    public function __construct(?CategoryStruct $activeCategory, array $tree)
    {
        $this->tree = $tree;
        $this->activeCategory = $activeCategory;
    }

    public function isCategorySelected(CategoryStruct $category): bool
    {
        if (!$this->activeCategory) {
            return false;
        }

        if ($category->getId() === $this->activeCategory->getId()) {
            return true;
        }

        return \in_array($category->getId(), $this->activeCategory->getPathArray(), true);
    }

    public function getTree(): array
    {
        return $this->tree;
    }

    public function setTree(array $tree): void
    {
        $this->tree = $tree;
    }

    public function getActiveCategory(): ?CategoryStruct
    {
        return $this->activeCategory;
    }

    public function setActiveCategory(?CategoryStruct $activeCategory): void
    {
        $this->activeCategory = $activeCategory;
    }
}
