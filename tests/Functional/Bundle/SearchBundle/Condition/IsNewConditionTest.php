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

use Shopware\Bundle\SearchBundle\Condition\IsNewCondition;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class IsNewConditionTest extends TestCase
{
    public function testIsNew()
    {
        $condition = new IsNewCondition();

        $this->search(
            [
                'first' => ['added' => date('Y-m-d', strtotime('-60 days'))],
                'second' => ['added' => date('Y-m-d', strtotime('-31 days'))],
                'third' => ['added' => '2011-01-01'],
                'fourth' => ['added' => date('Y-m-d', strtotime('-20 days'))],
                'fifth' => ['added' => date('Y-m-d')],
                'sixth' => ['added' => date('Y-m-d', strtotime('-30 days'))],
            ],
            ['fourth', 'fifth', 'sixth'],
            null,
            [$condition]
        );
    }

    /**
     * @param $number
     * @param \Shopware\Models\Category\Category                    $category
     * @param \Shopware\Context\Struct\ShopContext $context
     * @param array                                                 $data
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $data = []
    ) {
        $product = parent::getProduct($number, $context, $category);
        $product = array_merge($product, $data);

        return $product;
    }
}
