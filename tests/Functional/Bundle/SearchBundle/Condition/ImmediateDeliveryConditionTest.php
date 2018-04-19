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

use Shopware\Bundle\SearchBundle\Condition\ImmediateDeliveryCondition;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class ImmediateDeliveryConditionTest extends TestCase
{
    public function testNoStock()
    {
        $condition = new ImmediateDeliveryCondition();

        $this->search(
            [
                'first' => ['inStock' => 0, 'minPurchase' => 1],
                'second' => ['inStock' => 0, 'minPurchase' => 1],
                'third' => ['inStock' => 2, 'minPurchase' => 1],
                'fourth' => ['inStock' => 1, 'minPurchase' => 1],
            ],
            ['third', 'fourth'],
            null,
            [$condition]
        );
    }

    public function testMinPurchaseEquals()
    {
        $condition = new ImmediateDeliveryCondition();

        $this->search(
            [
                'first' => ['inStock' => 0, 'minPurchase' => 1],
                'second' => ['inStock' => 0, 'minPurchase' => 1],
                'third' => ['inStock' => 3, 'minPurchase' => 3],
                'fourth' => ['inStock' => 20, 'minPurchase' => 20],
            ],
            ['third', 'fourth'],
            null,
            [$condition]
        );
    }

    public function testSubVariantWithStock()
    {
        $condition = new ImmediateDeliveryCondition();

        $this->search(
            [
                'first' => ['inStock' => 0, 'minPurchase' => 1],
                'second' => ['inStock' => 0, 'minPurchase' => 1],
                'third' => ['inStock' => 1, 'minPurchase' => 1],
                'fourth' => ['createVariants' => true],
            ],
            ['third', 'fourth'],
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
        $data = ['inStock' => 0, 'minPurchase' => 1]
    ) {
        $product = parent::getProduct($number, $context, $category);

        $product['lastStock'] = true;
        $product['mainDetail'] = array_merge($product['mainDetail'], $data);

        return $product;
    }

    protected function createProduct(
        $number,
        ShopContext $context,
        Category $category,
        $additionally
    ) {
        if ($additionally['createVariants'] == true) {
            $fourth = $this->getProduct('fourth', $context, $category);
            $configurator = $this->helper->getConfigurator(
                $context->getCurrentCustomerGroup(),
                'fourth'
            );

            $fourth = array_merge($fourth, $configurator);
            foreach ($fourth['variants'] as &$variant) {
                $variant['inStock'] = 4;
                $variant['minPurchase'] = 3;
            }

            return $this->helper->createArticle($fourth);
        }

        return parent::createProduct(
                $number,
                $context,
                $category,
                $additionally
            );
    }
}
