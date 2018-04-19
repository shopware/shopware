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

use Shopware\Bundle\SearchBundle\Condition\SearchTermCondition;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class SearchTermConditionTest extends TestCase
{
    public function testSingleMatch()
    {
        $condition = new SearchTermCondition('Unit');

        $this->search(
            [
                'first' => 'Default Product',
                'second' => 'UnitTest Product',
                'third' => 'Custom Product',
            ],
            ['second'],
            null,
            [$condition]
        );
    }

    public function testMultipleMatch()
    {
        $condition = new SearchTermCondition('unit');

        $this->search(
            [
                'first' => 'Default Unit Product',
                'second' => 'Unit Test Product',
                'third' => 'Custom Product Unit',
                'fourth' => 'Custom produniuct',
            ],
            ['first', 'second', 'third'],
            null,
            [$condition]
        );
    }

    public function createProducts($products, ShopContext $context, Category $category)
    {
        $articles = parent::createProducts($products, $context, $category);

        Shopware()->Container()->get('shopware_searchdbal.search_indexer')->build();

        Shopware()->Container()->get('cache')->clean('all', ['Shopware_Modules_Search']);

        return $articles;
    }

    /**
     * @param $number
     * @param ShopContext $context
     * @param Category    $category
     * @param $name
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $name = null
    ) {
        $product = parent::getProduct($number, $context, $category);
        $product['name'] = $name;

        return $product;
    }
}
