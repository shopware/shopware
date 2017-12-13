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

namespace Shopware\Tests\Functional\Bundle\SearchBundle\Sorting;

use Shopware\Bundle\SearchBundle\Sorting\PopularitySorting;
use Shopware\Api\Entity\Search\SortingInterface;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class PopularitySortingTest extends TestCase
{
    public function testAscendingSorting()
    {
        $sorting = new PopularitySorting();

        $this->search(
            [
                'first' => 3,
                'second' => 20,
                'third' => 1,
            ],
            ['third', 'first', 'second'],
            null,
            [],
            [],
            [$sorting]
        );
    }

    public function testDescendingSorting()
    {
        $sorting = new PopularitySorting(SortingInterface::SORT_DESC);

        $this->search(
            [
                'first' => 3,
                'second' => 20,
                'third' => 1,
            ],
            ['second', 'first', 'third'],
            null,
            [],
            [],
            [$sorting]
        );
    }

    public function testSalesEquals()
    {
        $sorting = new PopularitySorting(SortingInterface::SORT_DESC);

        $this->search(
            [
                'first' => 3,
                'second' => 20,
                'third' => 1,
                'fourth' => 20,
            ],
            ['second', 'fourth', 'first', 'third'],
            null,
            [],
            [],
            [$sorting]
        );
    }

    protected function createProduct(
        $number,
        ShopContext $context,
        Category $category,
        $sales
    ) {
        $article = parent::createProduct(
            $number,
            $context,
            $category,
            $sales
        );

        Shopware()->Db()->query(
            'UPDATE s_articles_top_seller_ro SET sales = ?
             WHERE article_id = ?',
            [$sales, $article->getId()]
        );

        return $article;
    }

    protected function search(
        $products,
        $expectedNumbers,
        $category = null,
        $conditions = [],
        $facets = [],
        $sortings = [],
        $context = null,
        array $configs = []
    ) {
        $result = parent::search(
            $products,
            $expectedNumbers,
            $category,
            $conditions,
            $facets,
            $sortings,
            $context
        );

        $this->assertSearchResultSorting($result, $expectedNumbers);

        return $result;
    }
}
