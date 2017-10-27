<?php
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

namespace Shopware\Tests\Functional\Bundle\SearchBundle\Condition;

use Shopware\Api\Search\Condition\CategoryCondition;
use Shopware\Api\Search\Criteria;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class CategoryConditionTest extends TestCase
{
    public function testMultipleCategories()
    {
        $first = $this->helper->createCategory(['name' => 'first-category']);
        $second = $this->helper->createCategory(['name' => 'second-category']);

        $condition = new CategoryCondition([
            $first->getId(),
            $second->getId(),
        ]);

        $this->search(
            [
                'first' => $first,
                'second' => $second,
                'third' => null,
                'fourth' => $first,
            ],
            ['first', 'second', 'fourth'],
            null,
            [$condition]
        );
    }

    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $additionally = null
    ) {
        return parent::getProduct($number, $context, $additionally);
    }

    /**
     * Override prevents a default category condition
     *
     * @param Criteria $criteria
     * @param Category $category
     * @param $conditions
     * @param \Shopware\Context\Struct\ShopContext $context
     */
    protected function addCategoryBaseCondition(
        Criteria $criteria,
        Category $category,
        $conditions,
        ShopContext $context
    ) {
    }
}
