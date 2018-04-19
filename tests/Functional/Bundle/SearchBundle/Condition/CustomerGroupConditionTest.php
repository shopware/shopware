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

use Shopware\Bundle\SearchBundle\Condition\CustomerGroupCondition;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Models\Customer\Group;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class CustomerGroupConditionTest extends TestCase
{
    public function testSingleCustomerGroup()
    {
        $customerGroup = $this->helper->createCustomerGroup(['key' => 'CON']);

        $this->search(
            [
                'first' => [$customerGroup],
                'second' => [$customerGroup],
                'third' => null,
                'fourth' => null,
            ],
            ['third', 'fourth'],
            null,
            [new CustomerGroupCondition([$customerGroup->getId()])]
        );
    }

    public function testMultipleCustomerGroups()
    {
        $first = $this->helper->createCustomerGroup(['key' => 'CON']);
        $second = $this->helper->createCustomerGroup(['key' => 'CON2']);

        $condition = new CustomerGroupCondition([$first->getId(), $second->getId()]);

        $this->search(
            [
                'first' => [$first],
                'second' => [$second],
                'third' => [$first, $second],
                'fourth' => null,
            ],
            ['fourth'],
            null,
            [$condition]
        );
    }

    /**
     * @param $number
     * @param Group[]                                               $customerGroups
     * @param \Shopware\Models\Category\Category                    $category
     * @param \Shopware\Context\Struct\ShopContext $context
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $customerGroups = null
    ) {
        $product = parent::getProduct($number, $context, $category);

        $product['customerGroups'] = [];
        foreach ($customerGroups as $customerGroup) {
            $product['customerGroups'][] = ['id' => $customerGroup->getId()];
        }

        return $product;
    }
}
